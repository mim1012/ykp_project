import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css', 
                'resources/js/app.js',
                'resources/js/dashboard.jsx'
            ],
            refresh: true,
        }),
        react({
            // JSX 파일만 처리 (.js 파일 제외)
            include: /\.(jsx|tsx)$/,
            jsxRuntime: 'classic'
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['react', 'react-dom'],
                    animations: ['framer-motion'],
                    query: ['@tanstack/react-query'],
                    icons: ['lucide-react'],
                    virtualization: ['react-window', 'react-virtualized-auto-sizer']
                }
            }
        },
        chunkSizeWarningLimit: 1000,
        sourcemap: false,
        // Additional optimizations
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true
            }
        },
        // Enable modern browser features
        target: 'es2020'
    },
    server: {
        hmr: {
            host: 'localhost',
        },
    },
    // Dependency optimization
    optimizeDeps: {
        include: [
            'react', 
            'react-dom', 
            'framer-motion', 
            '@tanstack/react-query',
            'lucide-react'
        ],
        exclude: ['react-window', 'react-virtualized-auto-sizer']
    },
    // Preview optimization for production builds
    preview: {
        port: 4173,
        strictPort: true,
    }
});
