module.exports = function(grunt) {

  // load all grunt tasks with this command. No need to set grunt.loadNpmTasks(...) for each task separately;
  require('load-grunt-tasks')(grunt);

  // output time table
	require('time-grunt')(grunt);

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),

    // ========================================================================================
    // WATCH TASK
    // ========================================================================================
    watch: {
      scss: {
        files: ['assets/scss/**/*.scss'],
        tasks: ['default'],
        options: {
          spawn: false
        }
      },
      php: {
        files: ['*.php'],
        tasks: ['phpcsfixer'],
        options: {
          spawn: false
        }
      }
    },

    // ========================================================================================
    // SASS TASK
    // ========================================================================================
    sass: {
      dev: {
        options: {
          outputStyle: 'expanded',
          sourceMap: true

        },
        files: {
          'assets/css/style.css': ['assets/scss/style.scss']
        }
      }
    },

    // ========================================================================================
    // POSTCSS TASK
    // ========================================================================================
    postcss: {
      default: {
        options: {
          map: true,
          processors: [
            require('autoprefixer')({
              browsers: ['last 1 version', '> 2%'] // see http://browserl.ist/?q=last+1+version%2C+%3E+2%25
            })
          ]
        },
        src: ['assets/css/*.css']
      },
      dist: {
        options: {
          map: true,
          processors: [
            require('cssnano')() // add minified css
          ]
        },
        src: ['assets/css/*.css']
      }
    },

    // ========================================================================================
    // PHP CS FIXER
    // 
    // Source: https://github.com/FriendsOfPHP/PHP-CS-Fixer    //
    // Configurator: https://mlocati.github.io/php-cs-fixer-configurator/
    // ========================================================================================

    phpcsfixer: {
      app: {
          dir: ''
      },
      options: {
          bin: '../../vendor/bin/php-cs-fixer',
          configfile: '.php_cs',
          quiet: true
      }
    }
  }); // end of grunt configuration

  // ========================================================================================
  // TASKS
  // ========================================================================================
  // Default task(s)
  grunt.registerTask('default', ['sass:dev', 'postcss:default']);

  // Custom tasks (development)
  grunt.registerTask('_dev-module', ['sass:dev', 'postcss:default', 'phpcsfixer']);

  // Custom tasks (distribution)
  grunt.registerTask('_dist-module', ['_dev-module', 'postcss:dist']);
};
