import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';
import path from 'node:path';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
        },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
    },
});
