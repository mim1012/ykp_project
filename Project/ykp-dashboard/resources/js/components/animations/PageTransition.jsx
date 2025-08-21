import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { pageVariants, pageTransition } from '../../utils/animations';

// Page transition wrapper component
export const PageTransition = ({ 
  children, 
  key,
  variant = 'default',
  className = '',
  ...props 
}) => {
  const variants = {
    default: pageVariants,
    slide: {
      initial: { x: 300, opacity: 0 },
      in: { x: 0, opacity: 1 },
      out: { x: -300, opacity: 0 }
    },
    fade: {
      initial: { opacity: 0 },
      in: { opacity: 1 },
      out: { opacity: 0 }
    },
    scale: {
      initial: { scale: 0.8, opacity: 0 },
      in: { scale: 1, opacity: 1 },
      out: { scale: 1.2, opacity: 0 }
    }
  };

  const currentVariants = variants[variant] || variants.default;

  return (
    <motion.div
      key={key}
      initial="initial"
      animate="in"
      exit="out"
      variants={currentVariants}
      transition={pageTransition}
      className={`w-full ${className}`}
      {...props}
    >
      {children}
    </motion.div>
  );
};

// Animated route wrapper
export const AnimatedRoute = ({ 
  children, 
  routeKey, 
  transitionType = 'default',
  className = ''
}) => {
  return (
    <AnimatePresence mode="wait">
      <PageTransition 
        key={routeKey} 
        variant={transitionType}
        className={className}
      >
        {children}
      </PageTransition>
    </AnimatePresence>
  );
};

// Layout transition wrapper for persistent elements
export const LayoutTransition = ({ children, layoutId, className = '' }) => {
  return (
    <motion.div
      layoutId={layoutId}
      className={className}
      transition={{
        type: "spring",
        damping: 20,
        stiffness: 300
      }}
    >
      {children}
    </motion.div>
  );
};