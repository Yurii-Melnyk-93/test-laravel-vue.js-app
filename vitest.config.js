import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Kept separate from vite.config.js: that one is owned by the Laravel plugin,
// which expects to run inside a real request lifecycle.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
        globals: true,
        // Without this, call counts accumulate across tests in a file and an
        // assertion like "called once" silently measures the whole suite.
        clearMocks: true,
        restoreMocks: true,
    },
});
