/* eslint-env node */
const { nodeResolve } = require('@rollup/plugin-node-resolve');
const commonjs = require('@rollup/plugin-commonjs');
const { terser } = require('rollup-plugin-terser');

module.exports = function(grunt) {
    grunt.initConfig({
        rollup: {
            options: {
                format: 'amd',
                plugins: () => [
                    nodeResolve(),
                    commonjs(),
                    terser()
                ]
            },
            files: {
                expand: true,
                cwd: 'amd/src',
                src: ['**/*.js'],
                dest: 'amd/build',
                ext: '.min.js'
            }
        }
    });

    grunt.loadNpmTasks('grunt-rollup');

    grunt.registerTask('amd', ['rollup']);
};
