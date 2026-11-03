/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Figtree Variable"', 'Figtree', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
            },
            colors: {
                // Diambil dari logo Dyno Ads: biru → ungu → magenta atas kromium.
                ink: {
                    950: '#0a0715',
                    900: '#120c22',
                    850: '#181030',
                    800: '#1f1640',
                    700: '#2b1f56',
                },
                dyno: {
                    blue: '#1f9cf0',
                    indigo: '#5b4bd6',
                    purple: '#8b3fd6',
                    magenta: '#e6248f',
                    pink: '#ff4fb0',
                },
            },
            backgroundImage: {
                'dyno-gradient': 'linear-gradient(100deg, #1f9cf0 0%, #5b4bd6 38%, #8b3fd6 62%, #e6248f 100%)',
                'dyno-glow': 'radial-gradient(120% 80% at 50% 0%, rgba(139,63,214,0.35) 0%, rgba(10,7,21,0) 70%)',
            },
            boxShadow: {
                glow: '0 0 0 1px rgba(139,63,214,0.35), 0 8px 32px -8px rgba(230,36,143,0.45)',
            },
        },
    },
    plugins: [],
};
