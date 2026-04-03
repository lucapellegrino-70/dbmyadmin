const colors = require('tailwindcss/colors')

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './resources/views/**/*.blade.php',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: colors.violet,
        success: colors.emerald,
        danger:  colors.red,
        warning: colors.amber,
        info:    colors.blue,
      },
    },
  },
  plugins: [],
}
