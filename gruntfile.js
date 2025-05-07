'use strict';
module.exports = function (grunt) {

    // load all grunt tasks matching the `grunt-*` pattern
    // Ref. https://npmjs.org/package/load-grunt-tasks
    require('load-grunt-tasks')(grunt);
    grunt.initConfig({

        // Check text domain
        checktextdomain: {
            options: {
                text_domain: ['bp-group-moderation'], // Specify allowed domain(s)
                keywords: [ // List keyword specifications
                    '__:1,2d',
                    '_e:1,2d',
                    '_x:1,2c,3d',
                    'esc_html__:1,2d',
                    'esc_html_e:1,2d',
                    'esc_html_x:1,2c,3d',
                    'esc_attr__:1,2d',
                    'esc_attr_e:1,2d',
                    'esc_attr_x:1,2c,3d',
                    '_ex:1,2c,3d',
                    '_n:1,2,4d',
                    '_nx:1,2,4c,5d',
                    '_n_noop:1,2,3d',
                    '_nx_noop:1,2,3c,4d'
                ]
            },
            target: {
                files: [{
                    src: [
                        '*.php',
                        '**/*.php',
                        '!node_modules/**',
                        '!options/framework/**',
                        '!tests/**',
                        '!plugin-update-checker/**',
                    ], // all php
                    expand: true
                }]
            }
        },
        // make po files
        makepot: {
            target: {
                options: {
                    cwd: '.', // Directory of files to internationalize.
                    domainPath: 'languages/', // Where to save the POT file.
                    exclude: ['node_modules/*', 'options/framework/*', 'plugin-update-checker/*'], // List of files or directories to ignore.
                    mainFile: 'index.php', // Main project file.
                    potFilename: 'buddypress-group-moderation.pot', // Name of the POT file.
                    potHeaders: { // Headers to add to the generated POT file.
                        poedit: true, // Includes common Poedit headers.
                        'Last-Translator': 'Varun Dubey',
                        'Language-Team': 'Wbcom Designs',
                        'report-msgid-bugs-to': '',
                        'x-poedit-keywordslist': true // Include a list of all possible gettext functions.
                    },
                    type: 'wp-plugin', // Type of project (wp-plugin or wp-theme).
                    updateTimestamp: true // Whether the POT-Creation-Date should be updated without other changes.
                }
            }
        },
        // Task for CSS minification
        cssmin: {
            admin: {
                files: [{
                    expand: true,
                    cwd: 'assets/css/', // Source directory for admin CSS files
                    src: ['*.css', '!*.min.css', '!vendor/*.css'], // Minify all admin CSS files except already minified ones
                    dest: 'assets/css/', // Destination directory for minified admin CSS
                    ext: '.min.css', // Extension for minified files
                },
                {
                    expand: true,
                    cwd: 'assets/css-rtl/', // Source directory for RTL CSS files
                    src: ['*.css', '!*.min.css', '!vendor/*.css'], // Minify all .css files except already minified ones
                    dest: 'assets/css-rtl/', // Destination directory for minified CSS
                    ext: '.min.css' // Output file extension
                }],
            },
            wbcom: {
                files: [{
                    expand: true,
                    cwd: 'admin/wbcom/assets/css/', // Source directory for admin CSS files
                    src: ['*.css', '!*.min.css', '!vendor/*.css'], // Minify all admin CSS files except already minified ones
                    dest: 'admin/wbcom/assets/css/', // Destination directory for minified admin CSS
                    ext: '.min.css', // Extension for minified files
                },
                {
                    expand: true,
                    cwd: 'admin/wbcom/assets/css-rtl/', // Source directory for RTL CSS files
                    src: ['*.css', '!*.min.css', '!vendor/*.css'], // Minify all .css files except already minified ones
                    dest: 'admin/wbcom/assets/css-rtl/', // Destination directory for minified CSS
                    ext: '.min.css' // Output file extension
                }],
            },
        },
        // rtlcss
        rtlcss: {
            myTask: {
                options: {
                    // Generate source maps
                    map: { inline: false },
                    // RTL CSS options
                    opts: {
                        clean: false
                    },
                    // RTL CSS plugins
                    plugins: [],
                    // Save unmodified files
                    saveUnmodified: true,
                },
                files: [
                    {
                        expand: true,
                        cwd: 'assets/css', // Source directory for public CSS
                        src: ['**/*.css', '!**/*.min.css', '!vendor/**/*.css'], // Source files, excluding vendor CSS
                        dest: 'assets/css-rtl', // Destination directory for public RTL CSS
                        flatten: true // Prevents creating subdirectories
                    },
                    {
                        expand: true,
                        cwd: 'admin/wbcom/assets/css', // Source directory for public CSS
                        src: ['**/*.css', '!**/*.min.css', '!vendor/**/*.css'], // Source files, excluding vendor CSS
                        dest: 'admin/wbcom/assets/css-rtl', // Destination directory for public RTL CSS
                        flatten: true // Prevents creating subdirectories
                    },
                ]
            }
        },
        shell: {
            makepot_js: {
                command: 'wp i18n make-pot . languages/buddypress-group-moderation.pot',
            }
        },
        // JS minification (uglify)
        uglify: {
            admin: {
                options: {
                    mangle: false, // Prevents variable name mangling
                },
                files: [{
                    expand: true,
                    cwd: 'assets/js/', // Source directory for admin JS files
                    src: ['*.js', '!*.min.js', '!vendor/*.js'], // Minify all admin JS files except already minified ones
                    dest: 'assets/js/', // Destination directory for minified admin JS
                    ext: '.min.js', // Extension for minified files
                }],
            },
            wbcom: {
                options: {
                    mangle: false, // Prevents variable name mangling
                },
                files: [{
                    expand: true,
                    cwd: 'admin/wbcom/assets/js', // Source directory for admin JS files
                    src: ['*.js', '!*.min.js', '!vendor/*.js'], // Minify all admin JS files except already minified ones
                    dest: 'admin/wbcom/assets/js', // Destination directory for minified admin JS
                    ext: '.min.js', // Extension for minified files
                }],
            },
        },
        // Task for watching file changes
        watch: {
            css: {
                files: ['assets/css/*.css', '!assets/css/*.min.css'], // Watch for changes in frontend CSS files
                tasks: ['cssmin:admin'], // Run frontend CSS minification task
            },
            js: {
                files: ['assets/js/*.js', '!assets/js/*.min.js'], // Watch for changes in frontend JS files
                tasks: ['uglify:admin'], // Run frontend JS minification task
            },
            php: {
                files: ['**/*.php'], // Watch for changes in PHP files
                tasks: ['checktextdomain'], // Run text domain check
            },
        },
    });

    // register task  'checktextdomain', 'makepot',
    grunt.registerTask('default', ['checktextdomain', 'makepot', 'shell:makepot_js', 'rtlcss', 'cssmin', 'uglify', 'watch']);
};