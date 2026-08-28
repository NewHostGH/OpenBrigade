import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/ob-geolocation.js',
                'resources/js/ob-auth-login.js',
                'resources/js/ob-personnel-form.js',
                'resources/js/ob-personnel-index.js',
                'resources/js/ob-personnel-show.js',
                'resources/js/ob-event-form.js',
                'resources/js/ob-event-show.js',
                'resources/js/ob-dues-index.js',
                'resources/js/ob-vehicle-form.js',
                'resources/js/ob-vehicle-show.js',
                'resources/js/ob-organization-org-chart.js',
                'resources/js/ob-statistics-index.js',
                'resources/js/ob-statistics-report.js',
                'resources/js/ob-pdf-report.js',
                'resources/js/ob-pdf-personnel.js',
                'resources/js/ob-dashboard.js',
                'resources/js/ob-calendar.js',
                'resources/js/ob-section-planning.js',
                'resources/js/ob-availability.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        // ECharts (~575 kB) and pdf-lib (~430 kB) are unavoidably large, but each
        // is split into its own chunk and only loaded on the pages that use it.
        // Raise the warning ceiling above those page-scoped vendor chunks.
        chunkSizeWarningLimit: 650,
    },
});
