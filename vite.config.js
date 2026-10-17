import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/cms-editor.js'],
            publicDirectory: 'dist',
            buildDirectory: 'build',
            hotFile: '../../public/vendor/cms/hot',
            refresh: ['resources/views/**'],
        }),
    ],
});
