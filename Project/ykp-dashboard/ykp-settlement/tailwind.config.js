/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        'input-yellow': '#fef3c7',
        'input-yellow-focus': '#fde68a',
        'calc-gray': '#f3f4f6',
        'calc-gray-dark': '#e5e7eb',
      }
    },
  },
  plugins: [],
}