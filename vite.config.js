import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import collectModuleAssetsPaths from './vite-module-loader.js';

export default defineConfig(({ mode }) => {
    const isProduction = mode === 'production';

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/js/merchant-dashboard.jsx'
                ],
                refresh: !isProduction, // Disable refresh in production
            }),
            react(),
            tailwindcss(),
        ],
        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },
        build: {
            // Production optimizations
            minify: isProduction ? 'terser' : false,
            sourcemap: !isProduction,
            rollupOptions: {
                output: {
                    manualChunks: {
                        vendor: ['react', 'react-dom'],
                        charts: ['recharts'],
                    },
                },
            },
            ...(isProduction && {
                terserOptions: {
                    compress: {
                        drop_console: true,
                        drop_debugger: true,
                    },
                    mangle: {
                        safari10: true,
                    },
                },
            }),
        },
        server: {
            hmr: {
                host: 'localhost',
            },
        },
    };
});
