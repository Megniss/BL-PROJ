
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

const codespace = process.env.CODESPACE_NAME;

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/app.css',
            ],
            refresh: true,
        }),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        allowedHosts: true,
        cors: true,
        hmr: codespace
            ? {
                host: `${codespace}-5173.app.github.dev`,
                protocol: 'wss',
                clientPort: 443,
            }
            : undefined,
    },
});
