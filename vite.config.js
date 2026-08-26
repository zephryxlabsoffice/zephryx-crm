import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Fonts are not declared here on purpose. The @fonts directive emits
            // an inline <style> block, which would require 'unsafe-inline' in
            // the style-src CSP (spec §6). Plus Jakarta Sans is imported from
            // @fontsource in resources/css/app.css instead, so the faces land in
            // the same linked stylesheet as everything else.
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
