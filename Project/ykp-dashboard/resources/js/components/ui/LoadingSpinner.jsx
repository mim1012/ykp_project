import React from 'react';
import { motion } from 'framer-motion';

export const LoadingSpinner = ({ 
    size = "md", 
    message = "로딩 중...", 
    variant = "spinner",
    className = "",
    fullScreen = false
}) => {
    const sizes = {
        sm: "w-4 h-4",
        md: "w-8 h-8",
        lg: "w-12 h-12",
        xl: "w-16 h-16"
    };

    const spinnerVariants = {
        spinning: {
            rotate: 360,
            transition: {
                duration: 1,
                repeat: Infinity,
                ease: "linear"
            }
        }
    };

    const pulseVariants = {
        pulse: {
            scale: [1, 1.2, 1],
            opacity: [1, 0.6, 1],
            transition: {
                duration: 1.5,
                repeat: Infinity,
                ease: "easeInOut"
            }
        }
    };

    const dotsVariants = {
        bounce: {
            y: [0, -20, 0],
            transition: {
                duration: 0.6,
                repeat: Infinity,
                ease: "easeInOut"
            }
        }
    };

    const containerClass = fullScreen 
        ? "fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center"
        : "flex items-center justify-center h-64";

    const renderSpinner = () => {
        switch (variant) {
            case "pulse":
                return (
                    <motion.div
                        className={`rounded-full bg-blue-600 ${sizes[size]}`}
                        variants={pulseVariants}
                        animate="pulse"
                    />
                );
            
            case "dots":
                return (
                    <div className="flex space-x-1">
                        {[0, 1, 2].map((index) => (
                            <motion.div
                                key={index}
                                className="w-2 h-2 bg-blue-600 rounded-full"
                                variants={dotsVariants}
                                animate="bounce"
                                transition={{
                                    delay: index * 0.2,
                                    duration: 0.6,
                                    repeat: Infinity,
                                    ease: "easeInOut"
                                }}
                            />
                        ))}
                    </div>
                );
            
            case "ring":
                return (
                    <motion.div
                        className={`border-4 border-gray-200 border-t-blue-600 rounded-full ${sizes[size]}`}
                        variants={spinnerVariants}
                        animate="spinning"
                    />
                );
            
            default: // spinner
                return (
                    <motion.div
                        className={`rounded-full border-b-2 border-blue-600 ${sizes[size]}`}
                        variants={spinnerVariants}
                        animate="spinning"
                    />
                );
        }
    };

    return (
        <div className={`${containerClass} ${className}`}>
            <div className="flex flex-col items-center space-y-3">
                {renderSpinner()}
                {message && (
                    <motion.span 
                        className="text-gray-600 text-sm font-medium"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: 0.2 }}
                    >
                        {message}
                    </motion.span>
                )}
            </div>
        </div>
    );
};

// Progress bar loading component
export const ProgressBar = ({ 
    progress = 0, 
    message = "", 
    className = "",
    animated = true 
}) => {
    return (
        <div className={`w-full ${className}`}>
            <div className="flex justify-between items-center mb-2">
                {message && (
                    <span className="text-sm font-medium text-gray-700">{message}</span>
                )}
                <span className="text-sm text-gray-500">{Math.round(progress)}%</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2">
                <motion.div
                    className="bg-blue-600 h-2 rounded-full"
                    initial={{ width: 0 }}
                    animate={{ width: `${progress}%` }}
                    transition={animated ? { duration: 0.5, ease: "easeOut" } : { duration: 0 }}
                />
            </div>
        </div>
    );
};

// Skeleton with loading animation
export const SkeletonLoader = ({ 
    lines = 3, 
    className = "",
    avatar = false 
}) => {
    return (
        <div className={`animate-pulse ${className}`}>
            <div className="flex space-x-4">
                {avatar && (
                    <div className="rounded-full bg-gray-300 h-10 w-10"></div>
                )}
                <div className="flex-1 space-y-2 py-1">
                    {Array.from({ length: lines }).map((_, index) => (
                        <div
                            key={index}
                            className={`h-4 bg-gray-300 rounded ${
                                index === lines - 1 ? 'w-3/4' : 'w-full'
                            }`}
                        ></div>
                    ))}
                </div>
            </div>
        </div>
    );
};