import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Simie Home brand green (#7f9675), expanded into a Tailwind-style
                // shade scale so it can replace the default indigo/orange accents
                // used throughout the app (buttons, links, active nav state, etc).
                brand: {
                    50: '#f4f6f3',
                    100: '#e7ebe5',
                    200: '#cfd8cb',
                    300: '#b1c0ab',
                    400: '#93a88a',
                    500: '#7f9675',
                    600: '#6a8060',
                    700: '#57694f',
                    800: '#43523d',
                    900: '#303a2c',
                    950: '#1f261c',
                },
            },
        },
    },

    plugins: [forms],
};
