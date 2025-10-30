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
                    query: ['@tanstack/react-query', '@tanstack/react-query-devtools'],
                    icons: ['lucide-react'],
                    virtualization: ['react-window', 'react-virtualized-auto-sizer'],
                    aggrid: ['ag-grid-community', 'ag-grid-react'],
                    utils: ['axios']
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
        host: true,
        port: Number(process.env.VITE_PORT || 5173),
        hmr: {
            host: process.env.VITE_HMR_HOST || 'localhost',
            port: Number(process.env.VITE_HMR_PORT || process.env.VITE_PORT || 5173),
        },
    },
    // Dependency optimization
    optimizeDeps: {
        include: [
            'react', 
            'react-dom', 
            'framer-motion', 
            '@tanstack/react-query',
            '@tanstack/react-query-devtools',
            'lucide-react',
            'ag-grid-community',
            'ag-grid-react',
            'axios'
        ],
        exclude: ['react-window', 'react-virtualized-auto-sizer']
    },
    // Preview optimization for production builds
    preview: {
        port: 4173,
        strictPort: true,
    }
});
