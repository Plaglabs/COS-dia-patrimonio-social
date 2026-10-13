import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    // Vite corre dentro del contenedor (.docker/): escucha en todas las
    // interfaces para que el puerto publicado llegue, y le dice al navegador
    // que lo busque en localhost, no en la IP interna del contenedor.
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: { host: 'localhost' },
    },
});
