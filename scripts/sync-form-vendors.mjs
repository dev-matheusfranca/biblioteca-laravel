import { copyFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const assets = {
    'tom-select': [
        'dist/js/tom-select.base.min.js',
        'dist/js/tom-select.base.min.js.map',
        'dist/css/tom-select.default.min.css',
        'dist/css/tom-select.default.min.css.map',
        'LICENSE',
    ],
    flatpickr: [
        'dist/flatpickr.min.js',
        'dist/flatpickr.min.css',
        'dist/l10n/pt.js',
        'LICENSE.md',
    ],
};

// Keep the reviewed, pinned assets local: production does not need Node or a CDN.
for (const [dependency, files] of Object.entries(assets)) {
    const destination = resolve(root, 'public/vendor', dependency);
    await mkdir(destination, { recursive: true });
    for (const file of files) {
        await copyFile(
            resolve(root, 'node_modules', dependency, file),
            resolve(destination, file.split('/').at(-1)),
        );
    }
    console.log(`${dependency}: ${files.length} assets sincronizados.`);
}
