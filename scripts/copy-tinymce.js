const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const src = path.join(root, 'node_modules', 'tinymce');
const dest = path.join(root, 'public', 'vendor', 'tinymce');
const dirs = ['themes', 'skins', 'icons', 'models', 'plugins', 'langs'];

fs.mkdirSync(dest, { recursive: true });

for (const dir of dirs) {
    const from = path.join(src, dir);
    if (fs.existsSync(from)) {
        fs.cpSync(from, path.join(dest, dir), { recursive: true });
    }
}

fs.copyFileSync(path.join(src, 'tinymce.min.js'), path.join(dest, 'tinymce.min.js'));

const i18nEs = path.join(root, 'node_modules', 'tinymce-i18n', 'langs8', 'es.js');
if (fs.existsSync(i18nEs)) {
    fs.mkdirSync(path.join(dest, 'langs'), { recursive: true });
    fs.copyFileSync(i18nEs, path.join(dest, 'langs', 'es.js'));
}

console.log('TinyMCE assets copiados a public/vendor/tinymce');
