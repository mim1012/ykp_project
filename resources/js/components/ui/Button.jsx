import React from 'react';

export const Button = ({ children, variant = "primary", size = "md", className = "", ...props }) => {
    const variants = {
        primary: "bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500 active:bg-primary-800",
        secondary: "bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-gray-500 active:bg-gray-300",
        ghost: "text-gray-600 hover:bg-gray-100 focus:ring-gray-500 active:bg-gray-200",
        danger: "bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 active:bg-red-800"
    };
    const sizes = {
        sm: "px-3 py-1.5 text-sm min-h-[36px]",
        md: "px-4 py-2 min-h-[44px]",
        lg: "px-6 py-3 text-lg min-h-[48px]"
    };
    return (
        <button 
            className={`inline-flex items-center justify-center font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 transform active:scale-95 touch-manipulation ${variants[variant]} ${sizes[size]} ${className}`}
            {...props}
        >
            {children}
        </button>
    );
};