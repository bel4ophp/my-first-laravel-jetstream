import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import fs from 'fs';

// Load the .env file manually so we can use it outside of the export
const env = loadEnv('', process.cwd(), '');

const hasCert = fs.existsSync(env.VITE_SSL_CERT);

export default defineConfig({
    server: {
        // '0.0.0.0' allows access from outside the container
        host: '0.0.0.0', 
        port: 5173,
        cors: true,
        strictPort: true,
        origin: `${env.VITE_APP_URL}:5173`,
        https: hasCert ? {
            key: fs.readFileSync(env.VITE_SSL_KEY), 
            cert: fs.readFileSync(env.VITE_SSL_CERT),
        } : false,
        hmr: {
            // This is how the browser talks back to Vite for live updates
            host: env.VITE_APP_URL,
            protocol: hasCert ? 'wss' : 'ws',
        },
    },
    publicDir: 'public', // Points to your public folder relative to the config file
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
