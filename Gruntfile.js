module.exports = function(grunt) {
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    phpcs: {
      application: {
        dir: [
          'src',
          'tests',
          'web'
        ]
      },
      options: {
        bin: 'vendor/bin/phpcs',
        standard: 'vendor/drupal/coder/coder_sniffer/Drupal/ruleset.xml'
      }
    },
    phplint: {
      good: ['src/**.php', 'web/**.php', 'tests/**.php'],
    },
    phpunit: {
      classes: {
        dir: 'tests',
      },
      options: {
        bin: 'vendor/bin/phpunit',
        colors: true
      }
    },
    watch: {
      phpFiles: {
        files: ['web/**.php', 'src/**.php', 'tests/**.php'],
        tasks: ['phplint', 'phpunit', 'phpcs']
      }
    }
  });
  grunt.loadNpmTasks('grunt-phplint');
  grunt.loadNpmTasks('grunt-phpcs');
  grunt.loadNpmTasks('grunt-phpunit');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.registerTask('default', ['watch']);
};

