import { useQuery, useMutation, useInfiniteQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import * as api from '../utils/api';

// Dashboard data query
export const useDashboardData = (filters = {}) => {
  return useQuery({
    queryKey: ['dashboard', filters],
    queryFn: () => api.fetchDashboardData(filters),
    staleTime: 2 * 60 * 1000, // 2 minutes for dashboard data
    select: (data) => {
      // Transform data on client side to reduce server load
      return {
        ...data,
        kpis: data.kpis?.map(kpi => ({
          ...kpi,
          formattedValue: formatNumber(kpi.value),
          trend: calculateTrend(kpi.current, kpi.previous)
        }))
      };
    },
    // Enable background updates
    refetchInterval: 5 * 60 * 1000, // Refetch every 5 minutes
  });
};

// Sales data with infinite loading
export const useSalesData = (filters = {}) => {
  return useInfiniteQuery({
    queryKey: ['sales', filters],
    queryFn: ({ pageParam = 1 }) => api.fetchSales({ ...filters, page: pageParam }),
    getNextPageParam: (lastPage) => {
      return lastPage.hasNextPage ? lastPage.nextPage : undefined;
    },
    select: (data) => ({
      pages: data.pages,
      pageParams: data.pageParams,
      allSales: data.pages.flatMap(page => page.data)
    }),
    staleTime: 30 * 1000, // 30 seconds for frequently updated sales data
  });
};

// Store management queries
export const useStores = () => {
  return useQuery({
    queryKey: ['stores'],
    queryFn: api.fetchStores,
    staleTime: 10 * 60 * 1000, // 10 minutes for store data
  });
};

export const useStore = (storeId) => {
  return useQuery({
    queryKey: ['store', storeId],
    queryFn: () => api.fetchStore(storeId),
    enabled: !!storeId,
    staleTime: 5 * 60 * 1000,
  });
};

// Reports queries
export const useReports = (type, dateRange) => {
  return useQuery({
    queryKey: ['reports', type, dateRange],
    queryFn: () => api.fetchReports(type, dateRange),
    enabled: !!type && !!dateRange,
    staleTime: 1 * 60 * 1000, // 1 minute for reports
  });
};

// User profile query
export const useUserProfile = () => {
  return useQuery({
    queryKey: ['user', 'profile'],
    queryFn: api.fetchUserProfile,
    staleTime: 30 * 60 * 1000, // 30 minutes for user profile
  });
};

// Mutations for data updates
export const useUpdateSale = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: api.updateSale,
    onSuccess: (data, variables) => {
      // Update the sales list cache
      queryClient.setQueryData(['sales'], (oldData) => {
        if (!oldData) return oldData;
        return {
          ...oldData,
          pages: oldData.pages.map(page => ({
            ...page,
            data: page.data.map(sale => 
              sale.id === variables.id ? { ...sale, ...data } : sale
            )
          }))
        };
      });
      
      // Update individual sale cache if it exists
      queryClient.setQueryData(['sale', variables.id], data);
      
      // Invalidate dashboard to trigger refresh
      queryClient.invalidateQueries(['dashboard']);
    },
    onError: (error) => {
      console.error('Failed to update sale:', error);
    }
  });
};

export const useCreateSale = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: api.createSale,
    onSuccess: () => {
      // Invalidate and refetch sales and dashboard data
      queryClient.invalidateQueries(['sales']);
      queryClient.invalidateQueries(['dashboard']);
    }
  });
};

export const useDeleteSale = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: api.deleteSale,
    onSuccess: (_, saleId) => {
      // Remove from sales cache
      queryClient.setQueryData(['sales'], (oldData) => {
        if (!oldData) return oldData;
        return {
          ...oldData,
          pages: oldData.pages.map(page => ({
            ...page,
            data: page.data.filter(sale => sale.id !== saleId)
          }))
        };
      });
      
      // Remove individual sale cache
      queryClient.removeQueries(['sale', saleId]);
      
      // Invalidate dashboard
      queryClient.invalidateQueries(['dashboard']);
    }
  });
};

// Store mutations
export const useUpdateStore = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: api.updateStore,
    onSuccess: (data, variables) => {
      // Update stores list
      queryClient.setQueryData(['stores'], (oldData) => {
        if (!oldData) return oldData;
        return oldData.map(store => 
          store.id === variables.id ? { ...store, ...data } : store
        );
      });
      
      // Update individual store cache
      queryClient.setQueryData(['store', variables.id], data);
    }
  });
};

// Custom hook for prefetching data
export const usePrefetchData = () => {
  const queryClient = useQueryClient();
  
  const prefetchDashboard = useCallback((filters) => {
    queryClient.prefetchQuery({
      queryKey: ['dashboard', filters],
      queryFn: () => api.fetchDashboardData(filters),
      staleTime: 2 * 60 * 1000,
    });
  }, [queryClient]);
  
  const prefetchSales = useCallback((filters) => {
    queryClient.prefetchInfiniteQuery({
      queryKey: ['sales', filters],
      queryFn: ({ pageParam = 1 }) => api.fetchSales({ ...filters, page: pageParam }),
      getNextPageParam: (lastPage) => lastPage.hasNextPage ? lastPage.nextPage : undefined,
    });
  }, [queryClient]);
  
  return { prefetchDashboard, prefetchSales };
};

// Hook for managing cached data
export const useCacheManager = () => {
  const queryClient = useQueryClient();
  
  const clearCache = useCallback((pattern) => {
    queryClient.removeQueries(pattern);
  }, [queryClient]);
  
  const invalidateCache = useCallback((pattern) => {
    queryClient.invalidateQueries(pattern);
  }, [queryClient]);
  
  const getCachedData = useCallback((queryKey) => {
    return queryClient.getQueryData(queryKey);
  }, [queryClient]);
  
  const setCachedData = useCallback((queryKey, data) => {
    queryClient.setQueryData(queryKey, data);
  }, [queryClient]);
  
  return {
    clearCache,
    invalidateCache,
    getCachedData,
    setCachedData
  };
};

// Utility functions
const formatNumber = (value) => {
  if (typeof value !== 'number') return value;
  return new Intl.NumberFormat('ko-KR').format(value);
};

const calculateTrend = (current, previous) => {
  if (!previous || !current) return 'neutral';
  const change = ((current - previous) / previous) * 100;
  return change > 0 ? 'positive' : change < 0 ? 'negative' : 'neutral';
};