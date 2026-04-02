/** @type {import('tailwindcss').Config} */
module.exports = {
  // Tailwind sẽ scan các file này để tạo CSS
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}