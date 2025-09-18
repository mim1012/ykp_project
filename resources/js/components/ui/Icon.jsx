import React, { useEffect } from 'react';

export const Icon = ({ name, className = "w-5 h-5" }) => {
    useEffect(() => {
        // Only initialize lucide if it's available globally
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }, []);
    
    return <i data-lucide={name} className={className}></i>;
};