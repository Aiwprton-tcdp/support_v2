/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'media',
  content: [
    "./index.html",
    "./src/**/*.{html,vue,js,ts,jsx,tsx}",
    './node_modules/flowbite-vue/**/*.{js,jsx,ts,tsx}',
    './node_modules/flowbite/**/*.{js,jsx,ts,tsx}',
    "./resources/**/*.{vue,js,blade.php}",
  ],
  theme: {
    extend: {
      display: ["group-hover"],
    },
  },
  plugins: [
    require('flowbite/plugin')
  ],
}

