import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Entradas de compilación: todo lo que debe empaquetarse se importa desde estos puntos
 * (p. ej. `import '../css/app.css'` en app.js). Así Vite procesa todo `resources/js/**`
 * y `resources/css/**` que formen parte del grafo de dependencias.
 */
function collectInputs() {
  const inputs = {
    app: path.resolve(__dirname, 'resources/js/app.js'),
    'delegado-app': path.resolve(__dirname, 'resources/js/delegado-app.js'),
  };
  const cssDir = path.join(__dirname, 'resources', 'css');
  if (fs.existsSync(cssDir)) {
    for (const name of fs.readdirSync(cssDir)) {
      if (!name.endsWith('.css') || name === 'app.css') {
        continue;
      }
      const key = `css/${path.basename(name, '.css')}`;
      inputs[key] = path.resolve(cssDir, name);
    }
  }
  return inputs;
}

export default defineConfig({
  publicDir: false,
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
    },
  },
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: collectInputs(),
    },
  },
});
