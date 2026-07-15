// Minifies every JS file under assets/js/ into assets/dist/js/ (same filenames).
// Run via `npm run build:js` (or `npm run build` for CSS+JS together) before deploying.
const esbuild = require('esbuild');
const fs = require('fs');
const path = require('path');

const srcDir = path.join(__dirname, 'assets', 'js');
const outDir = path.join(__dirname, 'assets', 'dist', 'js');

fs.mkdirSync(outDir, { recursive: true });

for (const file of fs.readdirSync(srcDir)) {
  if (!file.endsWith('.js')) continue;
  esbuild.buildSync({
    entryPoints: [path.join(srcDir, file)],
    outfile: path.join(outDir, file),
    minify: true,
    target: 'es2018',
    logLevel: 'info',
  });
}

console.log('JS minified into assets/dist/js/');
