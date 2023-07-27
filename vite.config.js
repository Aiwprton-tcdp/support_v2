import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import alias from '@rollup/plugin-alias'
import { resolve } from 'path'
import vue from '@vitejs/plugin-vue'

function generateAlias(path = '') {
  return resolve(resolve(__dirname), './resources/js' + path)
}

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/main.js'],
      refresh: true,
    }),
    {
      name: 'blade',
      handleHotUpdate({ file, server }) {
        if (file.endsWith('.blade.php')) {
          server.ws.send({
            type: 'full-reload',
            path: '*',
          })
        }
      }
    },
    vue({
      template: {
        compilerOptions: {
          isCustomElement: tag => tag.includes('-')
        },
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      }
    }),
    alias(),
  ],
  server: {
    hmr: {
      // host: 'localhost',
      host: 'https://support.aiwprton.sms19.ru',
    },
  },
  resolve: {
    alias: [
      { find: '@', replacement: generateAlias() },
      { find: '@src', replacement: generateAlias() },
      { find: '@comps', replacement: generateAlias('/components') },
      { find: '@pages', replacement: generateAlias('/components/pages') },
      { find: '@temps', replacement: generateAlias('/components/templates') },
      { find: '@states', replacement: generateAlias('/components/templates/states') },
      { find: '@utils', replacement: generateAlias('/utils') },
      { find: '@assets', replacement: generateAlias('/assets') },
    ],
  },
  build: {
    rollupOptions: {}
  },
  optimizeDeps: {
    include: [
      'node_modules',
      'src/**/*.vue',
    ]
  },
  base: './',
})
