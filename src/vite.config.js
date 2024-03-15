import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    // generate .vite/manifest.json in outDir
    manifest: false,
    rollupOptions: {
      // overwrite default .html entry
      input: '/components/SourceConfig/initSourceConfig.jsx',
      output: {
        entryFileNames: '[name].js',
        assetFileNames: "[name].[ext]",
      }
    },
  },
})