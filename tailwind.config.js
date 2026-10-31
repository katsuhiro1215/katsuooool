import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: {
                    DEFAULT: '#0b0c0e',
                    900: '#0b0c0e',
                    800: '#15171a',
                    700: '#24262b',
                },
                gold: {
                    50: '#fdf8e7',
                    100: '#fbeec2',
                    300: '#f3d672',
                    400: '#eec43f',
                    500: '#e0ad1f',
                    600: '#b5890f',
                },
                sprout: {
                    50: '#eef9e8',
                    100: '#d9f0ce',
                    400: '#7fc957',
                    500: '#5fb33a',
                    600: '#45922a',
                },
            },
        },
    },

    plugins: [forms, typography],
};
