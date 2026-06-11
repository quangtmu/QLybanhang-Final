const fs = require('fs');

const content = fs.readFileSync('public/assets/css/global.css', 'utf8');
const lines = content.split('\n');

const parts = {
    'base.css': [1, 996],
    'ui.css': [997, 1185],
    'dashboard.css': [1186, 1514],
    'storefront.css': [1515, 1672]
};

fs.mkdirSync('public/assets/css/modules', { recursive: true });

const imports = [];
for (const [name, [start, end]] of Object.entries(parts)) {
    const slice = lines.slice(start - 1, end);
    fs.writeFileSync(`public/assets/css/modules/${name}`, slice.join('\n'));
    imports.push(`@import url('./modules/${name}');`);
}

fs.writeFileSync('public/assets/css/global.css', imports.join('\n'));
console.log('Split global.css successfully via Node');
