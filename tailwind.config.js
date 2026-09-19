import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                'page': 'var(--color-page)',
                'surface': 'var(--color-surface)',
                'surface-hover': 'var(--color-surface-hover)',
                'muted': 'var(--color-muted)',
                'primary': 'var(--color-primary)',
                'primary-hover': 'var(--color-primary-hover)',
                'on-primary': 'var(--color-on-primary)',
                'accent-soft': 'var(--color-accent-soft)',
                'accent-text': 'var(--color-accent-text)',
                'border': 'var(--color-border)',
                'overlay': 'var(--color-overlay)',
                'danger': 'var(--color-danger)',
                'danger-hover': 'var(--color-danger-hover)',
                'on-danger': 'var(--color-on-danger)',
                'error': 'var(--color-error)',
                'success': 'var(--color-success)',
                content: 'var(--color-text)',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
