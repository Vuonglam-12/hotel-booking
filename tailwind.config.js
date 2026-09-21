/** @type {import('tailwindcss').Config} */
module.exports = {
  // Tailwind sẽ scan các file này để tạo CSS
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Nunito Sans', 'system-ui', '-apple-system', 'sans-serif'],
      },
    },
  },
  plugins: [],
}