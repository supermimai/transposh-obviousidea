const fs = require('node:fs');
const terser = require('terser');

(async () => {
    const input = JSON.parse(fs.readFileSync(0, 'utf8'));
    const output = {};
    for (const [name, source] of Object.entries(input)) {
        const result = await terser.minify(source, {
            compress: true,
            mangle: true,
            format: { comments: /^!|@license|Copyright|SPDX/ }
        });
        output[name] = result.code;
    }
    process.stdout.write(JSON.stringify(output));
})().catch(error => { console.error(error.message); process.exit(1); });
