import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontend = path.resolve(__dirname, 'frontend');

export default defineConfig({
  plugins: [vue()],
  root: frontend,
  publicDir: false,
  server: {
    port: 5173,
    proxy: {
      '/api': { target: 'http://127.0.0.1:8888', changeOrigin: true },
    },
  },
  build: {
    outDir: path.resolve(__dirname, 'public/build'),
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: path.resolve(frontend, 'src/main.js'),
      output: {
        format: 'es',
        entryFileNames: 'panel.js',
        inlineDynamicImports: true,
        assetFileNames: (assetInfo) =>
          assetInfo.names?.[0]?.endsWith('.css') ? 'panel.css' : 'assets/[name]-[hash][extname]',
      },
    },
  },
});
