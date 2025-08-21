import React, { memo, useMemo, useCallback, useState, useRef, useEffect } from 'react';
import { FixedSizeList as List, VariableSizeList } from 'react-window';
import AutoSizer from 'react-virtualized-auto-sizer';
import { motion } from 'framer-motion';
import { useDebounce } from '../../hooks/usePerformance';

// Virtual list component for large datasets
export const VirtualList = memo(({
  items,
  renderItem,
  itemHeight = 50,
  className = '',
  onScroll,
  overscan = 5,
  searchTerm = '',
  searchKey = '',
  ...props
}) => {
  const debouncedSearchTerm = useDebounce(searchTerm, 300);
  
  const filteredItems = useMemo(() => {
    if (!debouncedSearchTerm || !searchKey) return items;
    
    return items.filter(item =>
      item[searchKey]?.toLowerCase().includes(debouncedSearchTerm.toLowerCase())
    );
  }, [items, debouncedSearchTerm, searchKey]);

  const Row = useCallback(({ index, style }) => {
    const item = filteredItems[index];
    
    return (
      <motion.div
        style={style}
        initial={{ opacity: 0, x: -20 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ duration: 0.2, delay: index * 0.01 }}
      >
        {renderItem(item, index)}
      </motion.div>
    );
  }, [filteredItems, renderItem]);

  return (
    <div className={`h-full ${className}`}>
      <AutoSizer>
        {({ height, width }) => (
          <List
            height={height}
            width={width}
            itemCount={filteredItems.length}
            itemSize={itemHeight}
            overscanCount={overscan}
            onScroll={onScroll}
            {...props}
          >
            {Row}
          </List>
        )}
      </AutoSizer>
    </div>
  );
});

VirtualList.displayName = 'VirtualList';

// Variable height virtual list
export const VariableVirtualList = memo(({
  items,
  renderItem,
  getItemHeight,
  className = '',
  estimatedItemHeight = 50,
  ...props
}) => {
  const listRef = useRef();
  const heightMap = useRef({});

  const setItemHeight = useCallback((index, height) => {
    if (heightMap.current[index] !== height) {
      heightMap.current[index] = height;
      if (listRef.current) {
        listRef.current.resetAfterIndex(index);
      }
    }
  }, []);

  const getHeight = useCallback((index) => {
    return heightMap.current[index] || estimatedItemHeight;
  }, [estimatedItemHeight]);

  const Row = useCallback(({ index, style }) => {
    const item = items[index];
    
    return (
      <div style={style}>
        <ItemWrapper
          index={index}
          setHeight={setItemHeight}
        >
          {renderItem(item, index)}
        </ItemWrapper>
      </div>
    );
  }, [items, renderItem, setItemHeight]);

  return (
    <div className={`h-full ${className}`}>
      <AutoSizer>
        {({ height, width }) => (
          <VariableSizeList
            ref={listRef}
            height={height}
            width={width}
            itemCount={items.length}
            itemSize={getHeight}
            estimatedItemSize={estimatedItemHeight}
            {...props}
          >
            {Row}
          </VariableSizeList>
        )}
      </AutoSizer>
    </div>
  );
});

VariableVirtualList.displayName = 'VariableVirtualList';

// Item wrapper to measure height
const ItemWrapper = memo(({ children, index, setHeight }) => {
  const ref = useRef();

  useEffect(() => {
    if (ref.current) {
      const height = ref.current.getBoundingClientRect().height;
      setHeight(index, height);
    }
  }, [children, index, setHeight]);

  return <div ref={ref}>{children}</div>;
});

ItemWrapper.displayName = 'ItemWrapper';

// Virtual table component
export const VirtualTable = memo(({
  data,
  columns,
  rowHeight = 60,
  headerHeight = 40,
  className = '',
  onRowClick,
  sortable = false,
  ...props
}) => {
  const [sortConfig, setSortConfig] = useState({ key: null, direction: 'asc' });

  const sortedData = useMemo(() => {
    if (!sortConfig.key) return data;

    return [...data].sort((a, b) => {
      const aValue = a[sortConfig.key];
      const bValue = b[sortConfig.key];

      if (aValue < bValue) {
        return sortConfig.direction === 'asc' ? -1 : 1;
      }
      if (aValue > bValue) {
        return sortConfig.direction === 'asc' ? 1 : -1;
      }
      return 0;
    });
  }, [data, sortConfig]);

  const handleSort = useCallback((key) => {
    if (!sortable) return;

    setSortConfig(prevConfig => ({
      key,
      direction: prevConfig.key === key && prevConfig.direction === 'asc' ? 'desc' : 'asc'
    }));
  }, [sortable]);

  const Header = memo(() => (
    <div 
      className="flex bg-gray-50 border-b border-gray-200"
      style={{ height: headerHeight }}
    >
      {columns.map((column) => (
        <div
          key={column.key}
          className={`
            flex items-center px-4 font-medium text-gray-700
            ${sortable && column.sortable !== false ? 'cursor-pointer hover:bg-gray-100' : ''}
          `}
          style={{ width: column.width || 'auto', flex: column.flex || 'none' }}
          onClick={() => column.sortable !== false && handleSort(column.key)}
        >
          {column.title}
          {sortable && sortConfig.key === column.key && (
            <span className="ml-1">
              {sortConfig.direction === 'asc' ? '↑' : '↓'}
            </span>
          )}
        </div>
      ))}
    </div>
  ));

  const Row = useCallback(({ index, style }) => {
    const item = sortedData[index];
    
    return (
      <motion.div
        style={style}
        className={`
          flex border-b border-gray-100 hover:bg-gray-50
          ${onRowClick ? 'cursor-pointer' : ''}
        `}
        onClick={() => onRowClick?.(item, index)}
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.2 }}
      >
        {columns.map((column) => (
          <div
            key={column.key}
            className="flex items-center px-4"
            style={{ width: column.width || 'auto', flex: column.flex || 'none' }}
          >
            {column.render ? column.render(item[column.key], item, index) : item[column.key]}
          </div>
        ))}
      </motion.div>
    );
  }, [sortedData, columns, onRowClick]);

  return (
    <div className={`border border-gray-200 rounded-lg overflow-hidden ${className}`}>
      <Header />
      <div style={{ height: `calc(100% - ${headerHeight}px)` }}>
        <AutoSizer>
          {({ height, width }) => (
            <List
              height={height}
              width={width}
              itemCount={sortedData.length}
              itemSize={rowHeight}
              {...props}
            >
              {Row}
            </List>
          )}
        </AutoSizer>
      </div>
    </div>
  );
});

VirtualTable.displayName = 'VirtualTable';

// Infinite loading virtual list
export const InfiniteVirtualList = memo(({
  items,
  renderItem,
  loadMore,
  hasNextPage,
  isLoading,
  itemHeight = 50,
  threshold = 5,
  className = '',
  ...props
}) => {
  const Row = useCallback(({ index, style }) => {
    const item = items[index];
    
    // Load more when approaching the end
    if (index >= items.length - threshold && hasNextPage && !isLoading) {
      loadMore();
    }
    
    if (!item) {
      return (
        <div style={style} className="flex items-center justify-center">
          <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600" />
        </div>
      );
    }
    
    return (
      <div style={style}>
        {renderItem(item, index)}
      </div>
    );
  }, [items, renderItem, loadMore, hasNextPage, isLoading, threshold]);

  const itemCount = hasNextPage ? items.length + 1 : items.length;

  return (
    <div className={`h-full ${className}`}>
      <AutoSizer>
        {({ height, width }) => (
          <List
            height={height}
            width={width}
            itemCount={itemCount}
            itemSize={itemHeight}
            {...props}
          >
            {Row}
          </List>
        )}
      </AutoSizer>
    </div>
  );
});

InfiniteVirtualList.displayName = 'InfiniteVirtualList';