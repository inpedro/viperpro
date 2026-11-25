/** @type {import('tailwindcss').Config} */
// eslint-disable-next-line no-undef
module.exports = {
    // The theme (or legacy layout) sets <html class="dark|light">; don't follow the OS setting.
    darkMode: 'class',
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./index.html",
        "./src/**/*.{vue,js,ts,jsx,tsx}",
        "./node_modules/flowbite/**/*.js",
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            borderRadius: { vp: 'var(--vp-radius)', 'vp-sm': 'var(--vp-radius-sm)' },
            fontFamily: { vp: 'var(--vp-font)', 'vp-heading': 'var(--vp-font-heading)' },
            colors: {
                // Design tokens (resources/views/layouts/app.blade.php, config/themes.php)
                vp: {
                    'bg': 'rgb(var(--vp-bg-rgb) / <alpha-value>)',
                    'surface': 'rgb(var(--vp-surface-rgb) / <alpha-value>)',
                    'surface-2': 'rgb(var(--vp-surface-2-rgb) / <alpha-value>)',
                    'border': 'rgb(var(--vp-border-rgb) / <alpha-value>)',
                    'text': 'rgb(var(--vp-text-rgb) / <alpha-value>)',
                    'muted': 'rgb(var(--vp-muted-rgb) / <alpha-value>)',
                    'primary': 'rgb(var(--vp-primary-rgb) / <alpha-value>)',
                    'primary-contrast': 'rgb(var(--vp-primary-contrast-rgb) / <alpha-value>)',
                    'accent': 'rgb(var(--vp-accent-rgb) / <alpha-value>)',
                    'success': 'rgb(var(--vp-success-rgb) / <alpha-value>)',
                    'warning': 'rgb(var(--vp-warning-rgb) / <alpha-value>)',
                    'danger': 'rgb(var(--vp-danger-rgb) / <alpha-value>)',
                },
                primary: {
                    100: '#F4F4F4',
                    200: '#E5E5E5',
                    300: '#D6D6D6',
                    400: '#C2C2C2',
                    500: '#A1A1A1',
                    600: '#7B7B7B',
                    700: '#5E5E5E',
                    800: '#3E3E3E',
                    900: '#2C2C2C',
                },
                secondary: {
                    100: '#FCE7E7',
                    200: '#F9C1C1',
                    300: '#F59E9E',
                    400: '#F06565',
                    500: '#EF4444',
                    600: '#D73737',
                    700: '#B82727',
                    800: '#962121',
                    900: '#7C1616',
                },
                green: {
                    100: '#ECFDF5',
                    200: '#D1FAE5',
                    300: '#A7F3D0',
                    400: '#4ce325',
                    500: '#3BC117',
                    600: '#32a614',
                    700: '#206e0e',
                    800: '#184b0b',
                    900: '#133b0b',
                },
                gray: {
                    100: '#F4F4F4',
                    200: '#e7e8e9',
                    300: '#D6D6D6',
                    400: '#98A7B5',
                    500: '#656E78',
                    600: '#3b3d44',
                    700: '#24262B',
                    800: '#191A1E',
                    900: '#1C1E22',
                },
            },
        },
    },
    plugins: [
        require('flowbite/plugin')
    ],
}

