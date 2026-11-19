import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    assetsInlineLimit: 0
  },
  server: {
    port: 5173,
    // En développement, l'API tourne en local sur le port 3000
    // En production, Traefik route /api vers le service landing-api
    proxy: { '/api': 'http://localhost:3000' }
  }
});