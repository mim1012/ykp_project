import { useEffect, useRef, useCallback } from 'react';

/**
 * Custom hook for handling touch gestures and interactions
 * Provides swipe detection, touch feedback, and mobile-optimized interactions
 */
export const useTouchGestures = ({
    onSwipeLeft = null,
    onSwipeRight = null,
    onSwipeUp = null,
    onSwipeDown = null,
    onTap = null,
    onLongPress = null,
    swipeThreshold = 50,
    longPressDelay = 500,
    tapDelay = 300
} = {}) => {
    const touchRef = useRef(null);
    const touchStartRef = useRef(null);
    const touchMoveRef = useRef(null);
    const longPressTimerRef = useRef(null);
    const tapTimerRef = useRef(null);

    const clearTimers = useCallback(() => {
        if (longPressTimerRef.current) {
            clearTimeout(longPressTimerRef.current);
            longPressTimerRef.current = null;
        }
        if (tapTimerRef.current) {
            clearTimeout(tapTimerRef.current);
            tapTimerRef.current = null;
        }
    }, []);

    const handleTouchStart = useCallback((e) => {
        const touch = e.touches[0];
        touchStartRef.current = {
            x: touch.clientX,
            y: touch.clientY,
            time: Date.now()
        };
        touchMoveRef.current = { x: touch.clientX, y: touch.clientY };

        // Start long press timer
        if (onLongPress) {
            longPressTimerRef.current = setTimeout(() => {
                onLongPress(e);
                clearTimers();
            }, longPressDelay);
        }

        // Add touch feedback
        if (touchRef.current) {
            touchRef.current.style.transform = 'scale(0.98)';
            touchRef.current.style.opacity = '0.8';
        }
    }, [onLongPress, longPressDelay, clearTimers]);

    const handleTouchMove = useCallback((e) => {
        if (!touchStartRef.current) return;

        const touch = e.touches[0];
        touchMoveRef.current = { x: touch.clientX, y: touch.clientY };

        // Calculate movement distance
        const deltaX = Math.abs(touch.clientX - touchStartRef.current.x);
        const deltaY = Math.abs(touch.clientY - touchStartRef.current.y);

        // Cancel long press if moved too much
        if ((deltaX > 10 || deltaY > 10) && longPressTimerRef.current) {
            clearTimeout(longPressTimerRef.current);
            longPressTimerRef.current = null;
        }
    }, []);

    const handleTouchEnd = useCallback((e) => {
        if (!touchStartRef.current || !touchMoveRef.current) return;

        const deltaX = touchMoveRef.current.x - touchStartRef.current.x;
        const deltaY = touchMoveRef.current.y - touchStartRef.current.y;
        const deltaTime = Date.now() - touchStartRef.current.time;

        // Remove touch feedback
        if (touchRef.current) {
            touchRef.current.style.transform = '';
            touchRef.current.style.opacity = '';
        }

        clearTimers();

        // Detect swipes
        if (Math.abs(deltaX) > swipeThreshold || Math.abs(deltaY) > swipeThreshold) {
            if (Math.abs(deltaX) > Math.abs(deltaY)) {
                // Horizontal swipe
                if (deltaX > 0 && onSwipeRight) {
                    onSwipeRight(e, { deltaX, deltaY, deltaTime });
                } else if (deltaX < 0 && onSwipeLeft) {
                    onSwipeLeft(e, { deltaX, deltaY, deltaTime });
                }
            } else {
                // Vertical swipe
                if (deltaY > 0 && onSwipeDown) {
                    onSwipeDown(e, { deltaX, deltaY, deltaTime });
                } else if (deltaY < 0 && onSwipeUp) {
                    onSwipeUp(e, { deltaX, deltaY, deltaTime });
                }
            }
        } else if (deltaTime < tapDelay && onTap) {
            // Detect tap
            onTap(e, { deltaX, deltaY, deltaTime });
        }

        touchStartRef.current = null;
        touchMoveRef.current = null;
    }, [onSwipeLeft, onSwipeRight, onSwipeUp, onSwipeDown, onTap, swipeThreshold, tapDelay, clearTimers]);

    const handleTouchCancel = useCallback(() => {
        // Remove touch feedback
        if (touchRef.current) {
            touchRef.current.style.transform = '';
            touchRef.current.style.opacity = '';
        }

        clearTimers();
        touchStartRef.current = null;
        touchMoveRef.current = null;
    }, [clearTimers]);

    useEffect(() => {
        const element = touchRef.current;
        if (!element) return;

        // Add touch event listeners
        element.addEventListener('touchstart', handleTouchStart, { passive: true });
        element.addEventListener('touchmove', handleTouchMove, { passive: true });
        element.addEventListener('touchend', handleTouchEnd, { passive: true });
        element.addEventListener('touchcancel', handleTouchCancel, { passive: true });

        // Cleanup
        return () => {
            element.removeEventListener('touchstart', handleTouchStart);
            element.removeEventListener('touchmove', handleTouchMove);
            element.removeEventListener('touchend', handleTouchEnd);
            element.removeEventListener('touchcancel', handleTouchCancel);
            clearTimers();
        };
    }, [handleTouchStart, handleTouchMove, handleTouchEnd, handleTouchCancel, clearTimers]);

    return touchRef;
};

/**
 * Hook for detecting mobile device and touch capabilities
 */
export const useDeviceDetection = () => {
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const isAndroid = /Android/.test(navigator.userAgent);
    
    return {
        isTouchDevice,
        isMobile,
        isIOS,
        isAndroid,
        supportsTouch: isTouchDevice
    };
};

/**
 * Hook for handling pull-to-refresh functionality
 */
export const usePullToRefresh = (onRefresh, threshold = 80) => {
    const containerRef = useRef(null);
    const startYRef = useRef(0);
    const currentYRef = useRef(0);
    const isDraggingRef = useRef(false);

    const handleTouchStart = useCallback((e) => {
        if (containerRef.current?.scrollTop === 0) {
            startYRef.current = e.touches[0].clientY;
            isDraggingRef.current = true;
        }
    }, []);

    const handleTouchMove = useCallback((e) => {
        if (!isDraggingRef.current) return;

        currentYRef.current = e.touches[0].clientY;
        const deltaY = currentYRef.current - startYRef.current;

        if (deltaY > 0 && containerRef.current?.scrollTop === 0) {
            e.preventDefault();
            // Add visual feedback here if needed
        }
    }, []);

    const handleTouchEnd = useCallback(() => {
        if (!isDraggingRef.current) return;

        const deltaY = currentYRef.current - startYRef.current;
        
        if (deltaY > threshold && onRefresh) {
            onRefresh();
        }

        isDraggingRef.current = false;
        startYRef.current = 0;
        currentYRef.current = 0;
    }, [onRefresh, threshold]);

    useEffect(() => {
        const element = containerRef.current;
        if (!element) return;

        element.addEventListener('touchstart', handleTouchStart, { passive: false });
        element.addEventListener('touchmove', handleTouchMove, { passive: false });
        element.addEventListener('touchend', handleTouchEnd, { passive: true });

        return () => {
            element.removeEventListener('touchstart', handleTouchStart);
            element.removeEventListener('touchmove', handleTouchMove);
            element.removeEventListener('touchend', handleTouchEnd);
        };
    }, [handleTouchStart, handleTouchMove, handleTouchEnd]);

    return containerRef;
};