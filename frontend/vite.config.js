import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Vite dev server runs on http://localhost:5173 by default.
// The backend API runs separately on http://localhost:8000.
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
  },
})
