import { useAnimation, useInView } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import { getAnimationConfig } from '../utils/animations';

// Hook for triggering animations when element comes into view
export const useInViewAnimation = (threshold = 0.1, triggerOnce = true) => {
  const ref = useRef(null);
  const isInView = useInView(ref, { 
    threshold,
    triggerOnce 
  });
  const controls = useAnimation();

  useEffect(() => {
    if (isInView) {
      controls.start('visible');
    } else if (!triggerOnce) {
      controls.start('hidden');
    }
  }, [isInView, controls, triggerOnce]);

  return { ref, controls, isInView };
};

// Hook for staggered list animations
export const useStaggerAnimation = (items, delay = 0.1) => {
  const controls = useAnimation();
  const [isVisible, setIsVisible] = useState(false);

  const startAnimation = () => {
    setIsVisible(true);
    controls.start(i => ({
      opacity: 1,
      y: 0,
      transition: {
        delay: i * delay,
        ...getAnimationConfig()
      }
    }));
  };

  const resetAnimation = () => {
    setIsVisible(false);
    controls.set({ opacity: 0, y: 20 });
  };

  return { controls, startAnimation, resetAnimation, isVisible };
};

// Hook for page transition animations
export const usePageTransition = () => {
  const controls = useAnimation();
  const [isTransitioning, setIsTransitioning] = useState(false);

  const startTransition = async () => {
    setIsTransitioning(true);
    await controls.start('out');
  };

  const completeTransition = async () => {
    await controls.start('in');
    setIsTransitioning(false);
  };

  const resetTransition = () => {
    controls.set('initial');
    setIsTransitioning(false);
  };

  return {
    controls,
    isTransitioning,
    startTransition,
    completeTransition,
    resetTransition
  };
};

// Hook for hover animations with better performance
export const useHoverAnimation = (hoverScale = 1.05, tapScale = 0.95) => {
  const [isHovered, setIsHovered] = useState(false);
  const [isTapped, setIsTapped] = useState(false);

  const hoverProps = {
    onMouseEnter: () => setIsHovered(true),
    onMouseLeave: () => setIsHovered(false),
    onMouseDown: () => setIsTapped(true),
    onMouseUp: () => setIsTapped(false),
    onMouseOut: () => {
      setIsHovered(false);
      setIsTapped(false);
    }
  };

  const animationProps = {
    animate: {
      scale: isTapped ? tapScale : isHovered ? hoverScale : 1,
      transition: getAnimationConfig()
    }
  };

  return { hoverProps, animationProps, isHovered, isTapped };
};

// Hook for loading state animations
export const useLoadingAnimation = (isLoading = false) => {
  const controls = useAnimation();

  useEffect(() => {
    if (isLoading) {
      controls.start({
        opacity: [0.3, 1, 0.3],
        transition: {
          duration: 1.5,
          repeat: Infinity,
          ease: "easeInOut"
        }
      });
    } else {
      controls.start({
        opacity: 1,
        transition: { duration: 0.2 }
      });
    }
  }, [isLoading, controls]);

  return controls;
};

// Hook for scroll-triggered animations
export const useScrollAnimation = (offset = 100) => {
  const [scrollY, setScrollY] = useState(0);
  const [isScrollingUp, setIsScrollingUp] = useState(false);

  useEffect(() => {
    let lastScrollY = window.scrollY;

    const updateScrollY = () => {
      const currentScrollY = window.scrollY;
      setScrollY(currentScrollY);
      setIsScrollingUp(currentScrollY < lastScrollY);
      lastScrollY = currentScrollY;
    };

    const throttledUpdateScrollY = throttle(updateScrollY, 16); // ~60fps
    window.addEventListener('scroll', throttledUpdateScrollY);

    return () => window.removeEventListener('scroll', throttledUpdateScrollY);
  }, []);

  const shouldShow = scrollY > offset;
  const headerOffset = isScrollingUp || scrollY < offset ? 0 : -100;

  return { scrollY, isScrollingUp, shouldShow, headerOffset };
};

// Hook for gesture-based animations (mobile swipe, etc.)
export const useGestureAnimation = () => {
  const [isDragging, setIsDragging] = useState(false);
  const [dragOffset, setDragOffset] = useState({ x: 0, y: 0 });

  const gestureProps = {
    drag: true,
    dragElastic: 0.1,
    dragConstraints: { left: 0, right: 0, top: 0, bottom: 0 },
    onDragStart: () => setIsDragging(true),
    onDragEnd: () => {
      setIsDragging(false);
      setDragOffset({ x: 0, y: 0 });
    },
    onDrag: (event, info) => {
      setDragOffset({ x: info.offset.x, y: info.offset.y });
    }
  };

  const animationProps = {
    animate: {
      scale: isDragging ? 1.05 : 1,
      rotateZ: dragOffset.x * 0.1,
      transition: getAnimationConfig()
    }
  };

  return { gestureProps, animationProps, isDragging, dragOffset };
};

// Utility function for throttling
function throttle(func, limit) {
  let inThrottle;
  return function() {
    const args = arguments;
    const context = this;
    if (!inThrottle) {
      func.apply(context, args);
      inThrottle = true;
      setTimeout(() => inThrottle = false, limit);
    }
  };
}