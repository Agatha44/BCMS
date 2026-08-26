import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

// https://vitejs.dev/config/
export default defineConfig({
  // node_modules is root-owned in some environments; keep Vite cache in project root
  cacheDir: path.resolve(__dirname, '.vite'),
  plugins: [react()],
  preview: {
    // Configure preview server to handle SPA routing
    // This ensures all routes fallback to index.html
    port: 4173,
    strictPort: true,
  },
  // For development server, Vite already handles SPA routing
  server: {
    port: 5173,
    strictPort: true,
    host: true,
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/printer': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/prepayment': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/sanctum': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
})
