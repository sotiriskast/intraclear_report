// tailwind.config.js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import colors from 'tailwindcss/colors';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                current: 'currentColor',
                transparent: 'transparent',
                white: '#FFFFFF',
                black: '#1C1C1C',
                primary: colors.indigo[600],
                secondary: '#80CAEE',
                body: '#64748B',
                bodydark: '#AEB7C0',
                bodydark1: '#DEE4EE',
                bodydark2: '#8A99AF',
                strokedark: '#2E3A47',
                'meta-1': '#DC3545',
                'meta-2': '#EFF2F7',
                'meta-3': '#10B981',
                'meta-4': '#313D4A',
                'meta-5': '#259AE6',
                'meta-6': '#FFBA00',
                'meta-7': '#FF6766',
                'meta-8': '#F0950C',
                'meta-9': '#E5E7EB',
                boxdark: '#24303F',
                'boxdark-2': '#1A222C',
                stroke: '#E2E8F0',
                gray: '#EFF4FB',
                graydark: '#333A47',
            },
            spacing: {
                4.5: '1.125rem',
                5.5: '1.375rem',
                6.5: '1.625rem',
                7.5: '1.875rem',
                8.5: '2.125rem',
                11.5: '2.875rem',
                12.5: '3.125rem',
                13: '3.25rem',
                14: '3.5rem',
                15: '3.75rem',
                72.5: '18.125rem',
                84.5: '21.125rem',
            },
            zIndex: {
                999999: '999999',
                99999: '99999',
                9999: '9999',
                999: '999',
                99: '99',
                9: '9',
                1: '1',
            },
            boxShadow: {
                default: '0px 8px 13px -3px rgba(0, 0, 0, 0.07)',
                card: '0px 1px 3px rgba(0, 0, 0, 0.12)',
                'card-2': '0px 1px 2px rgba(0, 0, 0, 0.05)',
                switcher:
                    '0px 2px 4px rgba(0, 0, 0, 0.2), inset 0px 2px 2px #FFFFFF, inset 0px -1px 1px rgba(0, 0, 0, 0.1)',
            },
            dropShadow: {
                1: '0px 1px 0px #E2E8F0',
                2: '0px 1px 4px rgba(0, 0, 0, 0.12)',
            },
            screens: {
                '2xsm': '375px',
                xsm: '425px',
                '3xl': '2000px',
            },
        },
    },

    plugins: [forms, typography],
};
