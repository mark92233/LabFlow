/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./HTML_Demo/**/*.{html,js}"],
  theme: {
    extend: {
      colors: {
        crimson: '#dc143c',
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
      }
    },
  },
  plugins: [],
}