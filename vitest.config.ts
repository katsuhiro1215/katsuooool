import { defineConfig } from 'vitest/config';
import path from 'node:path';

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
        },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
    },
});
