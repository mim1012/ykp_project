import { useCallback, useMemo, useRef, useEffect, useState } from 'react';

// Hook for memoizing expensive calculations
export const useMemoizedCalculation = (calculation, dependencies) => {
  return useMemo(() => {
    const startTime = performance.now();
    const result = calculation();
    const endTime = performance.now();
    
    if (process.env.NODE_ENV === 'development') {
      console.log(`Calculation took ${endTime - startTime} milliseconds`);
    }
    
    return result;
  }, dependencies);
};

// Hook for debouncing values (useful for search inputs)
export const useDebounce = (value, delay) => {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
};

// Hook for throttling function calls
export const useThrottle = (callback, delay) => {
  const throttleRef = useRef();
  const lastRun = useRef();

  return useCallback(
    (...args) => {
      if (lastRun.current && Date.now() - lastRun.current < delay) {
        clearTimeout(throttleRef.current);
        throttleRef.current = setTimeout(() => {
          callback(...args);
          lastRun.current = Date.now();
        }, delay);
      } else {
        callback(...args);
        lastRun.current = Date.now();
      }
    },
    [callback, delay]
  );
};

// Hook for memoizing event handlers
export const useEventCallback = (callback, dependencies) => {
  const callbackRef = useRef(callback);
  
  useEffect(() => {
    callbackRef.current = callback;
  }, [callback]);
  
  return useCallback((...args) => {
    return callbackRef.current(...args);
  }, dependencies);
};

// Hook for tracking component render performance
export const useRenderPerformance = (componentName) => {
  const renderCount = useRef(0);
  const lastRenderTime = useRef(performance.now());

  useEffect(() => {
    renderCount.current += 1;
    const currentTime = performance.now();
    
    if (process.env.NODE_ENV === 'development') {
      console.log(
        `${componentName} rendered ${renderCount.current} times. ` +
        `Time since last render: ${currentTime - lastRenderTime.current}ms`
      );
    }
    
    lastRenderTime.current = currentTime;
  });

  return renderCount.current;
};

// Hook for optimizing re-renders with shallow comparison
export const useShallowMemo = (factory, deps) => {
  const ref = useRef();
  
  return useMemo(() => {
    if (!ref.current || !shallowEqual(ref.current.deps, deps)) {
      ref.current = {
        value: factory(),
        deps
      };
    }
    return ref.current.value;
  }, deps);
};

// Shallow equality check
function shallowEqual(a, b) {
  if (a === b) return true;
  
  if (!a || !b) return false;
  
  const keysA = Object.keys(a);
  const keysB = Object.keys(b);
  
  if (keysA.length !== keysB.length) return false;
  
  for (let key of keysA) {
    if (a[key] !== b[key]) return false;
  }
  
  return true;
}

// Hook for intersection observer with performance optimization
export const useIntersectionObserver = (options = {}) => {
  const [isInView, setIsInView] = useState(false);
  const [entry, setEntry] = useState(null);
  const elementRef = useRef(null);
  
  const {
    threshold = 0,
    root = null,
    rootMargin = '0px',
    triggerOnce = false
  } = options;

  useEffect(() => {
    const element = elementRef.current;
    if (!element) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        const inView = entry.isIntersecting;
        setIsInView(inView);
        setEntry(entry);
        
        if (inView && triggerOnce) {
          observer.unobserve(element);
        }
      },
      {
        threshold,
        root,
        rootMargin
      }
    );

    observer.observe(element);

    return () => {
      observer.unobserve(element);
    };
  }, [threshold, root, rootMargin, triggerOnce]);

  return { elementRef, isInView, entry };
};

// Hook for optimizing large list rendering
export const useVirtualization = (items, itemHeight, containerHeight) => {
  const [scrollTop, setScrollTop] = useState(0);
  
  const visibleItems = useMemo(() => {
    const startIndex = Math.floor(scrollTop / itemHeight);
    const endIndex = Math.min(
      startIndex + Math.ceil(containerHeight / itemHeight) + 1,
      items.length
    );
    
    return {
      startIndex: Math.max(0, startIndex),
      endIndex,
      visibleItems: items.slice(startIndex, endIndex)
    };
  }, [items, itemHeight, containerHeight, scrollTop]);

  const onScroll = useCallback((event) => {
    setScrollTop(event.target.scrollTop);
  }, []);

  return {
    ...visibleItems,
    onScroll,
    totalHeight: items.length * itemHeight,
    offsetY: visibleItems.startIndex * itemHeight
  };
};

// Hook for managing component state with performance tracking
export const useOptimizedState = (initialState, componentName = 'Component') => {
  const [state, setState] = useState(initialState);
  const updateCount = useRef(0);
  
  const optimizedSetState = useCallback((newState) => {
    updateCount.current += 1;
    
    if (process.env.NODE_ENV === 'development') {
      console.log(`${componentName} state updated ${updateCount.current} times`);
    }
    
    setState(newState);
  }, [componentName]);
  
  return [state, optimizedSetState];
};