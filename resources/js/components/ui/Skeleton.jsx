import React from 'react';
import { motion } from 'framer-motion';
import { loadingVariants } from '../../utils/animations';

// Base skeleton component
export const Skeleton = ({ 
  className = '', 
  variant = 'rectangular',
  width,
  height,
  animation = true,
  ...props 
}) => {
  const baseClasses = "bg-gray-200 rounded";
  
  const variantClasses = {
    rectangular: "rounded",
    circular: "rounded-full",
    text: "rounded h-4",
    avatar: "rounded-full w-10 h-10"
  };

  const classes = `${baseClasses} ${variantClasses[variant]} ${className}`;
  
  const style = {
    width: width || (variant === 'avatar' ? '2.5rem' : '100%'),
    height: height || (variant === 'text' ? '1rem' : variant === 'avatar' ? '2.5rem' : '1.5rem'),
    ...props.style
  };

  if (animation) {
    return (
      <motion.div
        className={`${classes} loading-shimmer`}
        style={style}
        variants={loadingVariants}
        initial="start"
        animate="end"
        {...props}
      />
    );
  }

  return (
    <div
      className={`${classes} loading-shimmer`}
      style={style}
      {...props}
    />
  );
};

// Card skeleton component
export const CardSkeleton = ({ className = '', showHeader = true, lines = 3 }) => {
  return (
    <div className={`bg-white rounded-xl shadow-sm border border-gray-100 p-6 ${className}`}>
      {showHeader && (
        <div className="flex items-center justify-between mb-4">
          <Skeleton width="40%" height="1.5rem" />
          <Skeleton variant="circular" width="2rem" height="2rem" />
        </div>
      )}
      <div className="space-y-3">
        {Array.from({ length: lines }).map((_, index) => (
          <Skeleton 
            key={index}
            variant="text" 
            width={index === lines - 1 ? "70%" : "100%"}
          />
        ))}
      </div>
    </div>
  );
};

// Table skeleton component
export const TableSkeleton = ({ 
  rows = 5, 
  columns = 4, 
  className = '',
  showHeader = true 
}) => {
  return (
    <div className={`bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden ${className}`}>
      {showHeader && (
        <div className="border-b border-gray-200 p-4">
          <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
            {Array.from({ length: columns }).map((_, index) => (
              <Skeleton key={index} width="80%" height="1rem" />
            ))}
          </div>
        </div>
      )}
      <div className="divide-y divide-gray-200">
        {Array.from({ length: rows }).map((_, rowIndex) => (
          <div key={rowIndex} className="p-4">
            <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
              {Array.from({ length: columns }).map((_, colIndex) => (
                <Skeleton 
                  key={colIndex} 
                  width={colIndex === 0 ? "60%" : "100%"} 
                  height="1rem" 
                />
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

// Chart skeleton component
export const ChartSkeleton = ({ className = '', height = 300 }) => {
  return (
    <div className={`bg-white rounded-xl shadow-sm border border-gray-100 p-6 ${className}`}>
      <div className="flex items-center justify-between mb-6">
        <Skeleton width="30%" height="1.5rem" />
        <div className="flex space-x-2">
          <Skeleton width="4rem" height="2rem" />
          <Skeleton width="4rem" height="2rem" />
        </div>
      </div>
      <div className="relative" style={{ height }}>
        {/* Y-axis labels */}
        <div className="absolute left-0 top-0 h-full flex flex-col justify-between">
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} width="2rem" height="0.75rem" />
          ))}
        </div>
        
        {/* Chart area */}
        <div className="ml-10 h-full relative">
          <Skeleton className="absolute inset-0" />
          
          {/* X-axis labels */}
          <div className="absolute bottom-0 left-0 right-0 flex justify-between mt-4">
            {Array.from({ length: 7 }).map((_, index) => (
              <Skeleton key={index} width="2rem" height="0.75rem" />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

// KPI Card skeleton component
export const KPICardSkeleton = ({ className = '' }) => {
  return (
    <div className={`bg-white rounded-xl shadow-sm border border-gray-100 p-6 ${className}`}>
      <div className="flex items-center justify-between">
        <div className="flex-1">
          <Skeleton width="70%" height="1rem" className="mb-2" />
          <Skeleton width="50%" height="2rem" />
        </div>
        <Skeleton variant="circular" width="3rem" height="3rem" />
      </div>
      <div className="mt-4 flex items-center">
        <Skeleton width="30%" height="0.875rem" />
        <Skeleton width="2rem" height="0.875rem" className="ml-2" />
      </div>
    </div>
  );
};

// List skeleton component
export const ListSkeleton = ({ 
  items = 5, 
  className = '',
  showAvatar = false,
  showActions = false 
}) => {
  return (
    <div className={`bg-white rounded-xl shadow-sm border border-gray-100 ${className}`}>
      <div className="divide-y divide-gray-200">
        {Array.from({ length: items }).map((_, index) => (
          <div key={index} className="p-4 flex items-center space-x-4">
            {showAvatar && <Skeleton variant="avatar" />}
            <div className="flex-1 space-y-2">
              <Skeleton width="40%" height="1rem" />
              <Skeleton width="80%" height="0.875rem" />
            </div>
            {showActions && (
              <div className="flex space-x-2">
                <Skeleton width="4rem" height="2rem" />
                <Skeleton width="4rem" height="2rem" />
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
};

// Dashboard skeleton layout
export const DashboardSkeleton = () => {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Skeleton width="20%" height="2rem" />
        <Skeleton width="8rem" height="2.5rem" />
      </div>
      
      {/* KPI Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {Array.from({ length: 4 }).map((_, index) => (
          <KPICardSkeleton key={index} />
        ))}
      </div>
      
      {/* Charts and tables */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <ChartSkeleton />
        <TableSkeleton rows={6} columns={3} />
      </div>
    </div>
  );
};