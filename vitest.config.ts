import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';
import path from 'node:path';

export default defineConfig({
    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
        },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        setupFiles: ['resources/js/testing/vitest.setup.ts'],
    },
});
