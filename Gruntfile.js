const fs = require('fs');
const path = require('path');
const { rollup } = require('rollup');
const { nodeResolve } = require('@rollup/plugin-node-resolve');
const commonjs = require('@rollup/plugin-commonjs');
const { terser } = require('rollup-plugin-terser');

module.exports = function (grunt) {
    grunt.registerTask('amd', 'Build AMD modules', async function () {
        const done = this.async();
        const srcDir = 'amd/src';
        const destDir = 'amd/build';

        if (!fs.existsSync(destDir)) {
            fs.mkdirSync(destDir, { recursive: true });
        }

        const files = fs.readdirSync(srcDir).filter(file => file.endsWith('.js'));

        try {
            for (const file of files) {
                const inputPath = path.join(srcDir, file);
                const outputPath = path.join(destDir, file.replace('.js', '.min.js'));

                const bundle = await rollup({
                    input: inputPath,
                    plugins: [
                        nodeResolve(),
                        commonjs(),
                        terser()
                    ]
                });

                await bundle.write({
                    file: outputPath,
                    format: 'amd'
                });

                grunt.log.writeln(`✔ Built ${file} → ${outputPath}`);
            }
            done();
        } catch (err) {
            grunt.log.error(err);
            done(false);
        }
    });
};
