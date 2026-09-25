import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { resolve } from 'node:path';

const root = fileURLToPath(new URL('../', import.meta.url));
const files = {
    'tom-select': ['dist/js/tom-select.base.min.js', 'dist/js/tom-select.base.min.js.map', 'dist/css/tom-select.default.min.css', 'dist/css/tom-select.default.min.css.map', 'LICENSE'],
    flatpickr: ['dist/flatpickr.min.js', 'dist/flatpickr.min.css', 'dist/l10n/pt.js', 'LICENSE.md'],
};
for (const [dependency, paths] of Object.entries(files)) {
    for (const file of paths) {
        const installed = await readFile(resolve(root, 'node_modules', dependency, file));
        const published = await readFile(resolve(root, 'public/vendor', dependency, file.split('/').at(-1)));
        if (!installed.equals(published)) throw new Error(`Asset divergente: ${dependency}/${file}`);
    }
}
console.log('Assets publicados correspondem às dependências fixadas.');
