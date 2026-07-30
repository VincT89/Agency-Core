import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        chunkSizeWarningLimit: 550,
        rolldownOptions: {
            output: {
                codeSplitting: {
                    groups: [
                        {
                            name: 'icons',
                            test: /node_modules[\\/]lucide[\\/]/,
                        },
                    ],
                },
            },
        },
    },
    server: {
        host: '127.0.0.1',
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/charts.js'],
            refresh: true,
        }),
    ],
});
