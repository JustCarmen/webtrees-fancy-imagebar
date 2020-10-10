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

/**
 * Laravel mix entry point
 *
 * Load the appropriate section
 */

if (process.env.section) {
  require(`${__dirname}/webpack.mix.${process.env.section}.js`);
}

// Disable mix-manifest.json (https://github.com/JeffreyWay/laravel-mix/issues/580)
// Prevent the distribution zip file containing an unwanted file
Mix.manifest.refresh = _ => void 0

let mix = require('laravel-mix');
require('laravel-mix-clean');

// https://github.com/postcss/autoprefixer
const postcss_autoprefixer = require("autoprefixer")();

// https://github.com/jakob101/postcss-inline-rtl
const postcss_rtl = require("postcss-rtl")();

const dist_dir = 'dist/jc-fancy-imagebar';

//https://github.com/gregnb/filemanager-webpack-plugin
const FileManagerPlugin = require('filemanager-webpack-plugin');

if (process.env.NODE_ENV === 'production') {

  mix
    .setPublicPath('./dist')
    .options({
      processCssUrls: false,
      postCss: [
        postcss_rtl,
        postcss_autoprefixer,
      ]
    })
    .postCss("resources/css/style.css", dist_dir + '/resources/css/style.css')
    .copyDirectory('resources/views', dist_dir + '/resources/views')
    .copy('resources/lang/*.mo', dist_dir + '/resources/lang')
    .copy('FancyImagebarModule.php', dist_dir)
    .copy('module.php', dist_dir)
    .copy('LICENSE.md', dist_dir)
    .copy('README.md', dist_dir)
    .webpackConfig({
      plugins: [
        new FileManagerPlugin({
          onEnd: {
            archive: [{
              source: './dist',
              destination: 'dist/fancy-imagebar-2.0.9.zip'
            }]
          }
        })
      ]
    })
    .clean();
} else {
  mix
    .setPublicPath('./')
    .sass('src/sass/style.scss', 'resources/css/style.css')
    .options({
      processCssUrls: false,
      postCss: [
        postcss_rtl,
        postcss_autoprefixer
      ]
    });
}
