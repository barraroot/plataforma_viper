import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import i18n from 'laravel-vue-i18n/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
        }),
        vue({
            template: {
                base: null,
                includeAbsolute: true
            }
        }),
        i18n(),
    ],
    server: {
        watch: {
            usePolling: true,
        }
    },
    /*
    define: {
        global: {

        }
    }
    */
});