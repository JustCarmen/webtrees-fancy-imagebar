/**
 * Laravel mix - Fancy Imagebar
 *
 * Output:
 * 		- dist
 *        - resources
 *          - css
 *          - lang (.mo files)
 *          - views
 *        FancyImagebarModule.php
 *        module.php
 *        LICENSE.md
 *        README.md
 *
 */

let mix = require('laravel-mix');
let config = require('./webpack.mix.config');

// https://github.com/postcss/autoprefixer
const postcss_autoprefixer = require("autoprefixer")();

//https://github.com/jakob101/postcss-inline-rtl
const postcss_rtl = require("@mjhenkes/postcss-rtl")();

const dist_dir = 'dist/jc-fancy-imagebar';

//https://github.com/gregnb/filemanager-webpack-plugin
const FileManagerPlugin = require('filemanager-webpack-plugin');

if (process.env.NODE_ENV === 'production') {
  mix.styles(config.public_dir + '/css/style.css', config.build_dir + '/style.css')
} else {
    mix
        .setPublicPath('./')
        .sass('src/sass/style.scss', config.public_dir + '/css/style.css')
        .options({
            processCssUrls: false,
            postCss: [
                postcss_rtl,
                postcss_autoprefixer
            ]
        });
}
