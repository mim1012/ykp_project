import React from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

// Configure React Query client with optimized settings
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Stale time: Data is considered fresh for 5 minutes
      staleTime: 5 * 60 * 1000,
      // Cache time: Data stays in cache for 10 minutes after becoming unused
      cacheTime: 10 * 60 * 1000,
      // Retry failed requests 3 times with exponential backoff
      retry: 3,
      // Retry delay increases exponentially (1s, 2s, 4s)
      retryDelay: attemptIndex => Math.min(1000 * 2 ** attemptIndex, 30000),
      // Disable refetch on window focus to prevent constant refreshing
      refetchOnWindowFocus: false,
      // Don't refetch on reconnect by default to prevent spam
      refetchOnReconnect: false,
      // Enable background refetching only on mount
      refetchOnMount: 'always',
      // Network mode: online only (pause queries when offline)
      networkMode: 'online'
    },
    mutations: {
      // Retry mutations once on failure
      retry: 1,
      // Network mode for mutations
      networkMode: 'online'
    }
  }
});

// Query provider with error boundary
export const QueryProvider = ({ children }) => {
  return (
    <QueryClientProvider client={queryClient}>
      {children}
      {process.env.NODE_ENV === 'development' && (
        <ReactQueryDevtools 
          initialIsOpen={false}
          position="bottom-right"
        />
      )}
    </QueryClientProvider>
  );
};

// Export query client for use in other parts of the app
export { queryClient };