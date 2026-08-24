/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#962E32',
          50: '#fff5f5',
          100: '#fde7e8',
          200: '#fac6c8',
          300: '#f29ea1',
          400: '#e76b6f',
          500: '#962E32',
          600: '#7A2326',
          700: '#5d1c1f',
          800: '#3f1416',
          900: '#290e0f',
        },
      },
      ringColor: {
        DEFAULT: '#962E32',
      },
      outlineColor: {
        DEFAULT: '#962E32',
      },
    },
  },
  plugins: [],
}

