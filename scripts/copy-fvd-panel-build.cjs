/**
 * Tras `vite build` en fvd_panel, duplica public/build → fvd_portal/public/build
 * para que atletas.php y el panel sirvan el mismo bundle bajo APP_BASE_PATH.
 */
const fs = require('fs');
const path = require('path');

const portalRoot = path.resolve(__dirname, '..');
const src = path.resolve(portalRoot, '..', 'fvd_panel', 'public', 'build');
const dest = path.join(portalRoot, 'public', 'build');

if (!fs.existsSync(src)) {
  console.error('[copy-fvd-panel-build] No existe la carpeta de build:', src);
  console.error('  Ejecute antes: npm --prefix ../fvd_panel run build');
  process.exit(1);
}

fs.mkdirSync(path.dirname(dest), { recursive: true });
fs.cpSync(src, dest, { recursive: true });
console.log('[copy-fvd-panel-build] OK →', dest);
