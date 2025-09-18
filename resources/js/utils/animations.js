// Animation variants and utilities for consistent animations across the app

// Page transition variants
export const pageVariants = {
  initial: {
    opacity: 0,
    y: 20,
    scale: 0.98
  },
  in: {
    opacity: 1,
    y: 0,
    scale: 1
  },
  out: {
    opacity: 0,
    y: -20,
    scale: 0.98
  }
};

export const pageTransition = {
  type: "tween",
  ease: "anticipate",
  duration: 0.3
};

// Slide variants for mobile menus and modals
export const slideVariants = {
  fromLeft: {
    initial: { x: "-100%", opacity: 0 },
    animate: { x: 0, opacity: 1 },
    exit: { x: "-100%", opacity: 0 }
  },
  fromRight: {
    initial: { x: "100%", opacity: 0 },
    animate: { x: 0, opacity: 1 },
    exit: { x: "100%", opacity: 0 }
  },
  fromTop: {
    initial: { y: "-100%", opacity: 0 },
    animate: { y: 0, opacity: 1 },
    exit: { y: "-100%", opacity: 0 }
  },
  fromBottom: {
    initial: { y: "100%", opacity: 0 },
    animate: { y: 0, opacity: 1 },
    exit: { y: "100%", opacity: 0 }
  }
};

// Card and component animations
export const cardVariants = {
  hidden: {
    opacity: 0,
    y: 20,
    scale: 0.95
  },
  visible: {
    opacity: 1,
    y: 0,
    scale: 1,
    transition: {
      type: "spring",
      damping: 20,
      stiffness: 300
    }
  },
  hover: {
    y: -5,
    scale: 1.02,
    transition: {
      type: "spring",
      damping: 15,
      stiffness: 300
    }
  },
  tap: {
    scale: 0.98
  }
};

// List item stagger animations
export const listVariants = {
  hidden: {
    opacity: 0
  },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: 0.1,
      delayChildren: 0.1
    }
  }
};

export const listItemVariants = {
  hidden: {
    opacity: 0,
    x: -20
  },
  visible: {
    opacity: 1,
    x: 0,
    transition: {
      type: "spring",
      damping: 20,
      stiffness: 300
    }
  }
};

// Loading and skeleton animations
export const loadingVariants = {
  start: {
    opacity: 0.3
  },
  end: {
    opacity: 1,
    transition: {
      duration: 0.8,
      repeat: Infinity,
      repeatType: "reverse"
    }
  }
};

// Button micro-interactions
export const buttonVariants = {
  idle: {
    scale: 1,
    boxShadow: "0 1px 3px rgba(0, 0, 0, 0.1)"
  },
  hover: {
    scale: 1.05,
    boxShadow: "0 4px 15px rgba(0, 0, 0, 0.15)",
    transition: {
      type: "spring",
      damping: 15,
      stiffness: 300
    }
  },
  tap: {
    scale: 0.95,
    boxShadow: "0 1px 3px rgba(0, 0, 0, 0.1)"
  }
};

// Modal and overlay animations
export const modalVariants = {
  hidden: {
    opacity: 0,
    scale: 0.8,
    y: 50
  },
  visible: {
    opacity: 1,
    scale: 1,
    y: 0,
    transition: {
      type: "spring",
      damping: 20,
      stiffness: 300
    }
  },
  exit: {
    opacity: 0,
    scale: 0.8,
    y: 50,
    transition: {
      duration: 0.2
    }
  }
};

export const overlayVariants = {
  hidden: {
    opacity: 0
  },
  visible: {
    opacity: 1,
    transition: {
      duration: 0.2
    }
  },
  exit: {
    opacity: 0,
    transition: {
      duration: 0.2
    }
  }
};

// Chart animation utilities
export const chartAnimationConfig = {
  tension: 0.4,
  animation: {
    duration: 1000,
    easing: 'easeOutQuart'
  },
  responsive: true,
  maintainAspectRatio: false
};

// Utility function to create spring animations
export const springConfig = {
  type: "spring",
  damping: 20,
  stiffness: 300,
  mass: 0.5
};

// Reduced motion preferences
export const getAnimationConfig = (respectReducedMotion = true) => {
  if (respectReducedMotion && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return {
      type: "tween",
      duration: 0.1,
      ease: "linear"
    };
  }
  return springConfig;
};

// Intersection observer animation trigger
export const createInViewAnimation = (threshold = 0.1) => ({
  hidden: { opacity: 0, y: 50 },
  visible: {
    opacity: 1,
    y: 0,
    transition: getAnimationConfig()
  }
});