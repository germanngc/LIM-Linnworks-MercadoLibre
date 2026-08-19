/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
	darkMode: 'class',
  theme: {
    extend: {},
  },
  plugins: [
    require("@tailwindcss/forms"),
		require("flowbite/plugin"),
  ],
}
