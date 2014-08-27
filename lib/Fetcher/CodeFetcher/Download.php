<?php

namespace Fetcher\CodeFetcher;
use Symfony\Component\Process\Process;

class Download implements \Fetcher\CodeFetcher\SetupInterface {

  private $site = FALSE; 

  public function __construct(\Fetcher\Site $site) {
    $this->site = $site;
  }

  /**
   * Implements \Fetcher\CodeFetcher\SetupInterface::Setup().
   */
  public function setup() {

    $site = $this->site;

    $commandline_args = array(
      $site['profile'],
    );
    $commandline_options = array(
      // Default our package hander to git_drupalorg.
      //'--package-handler=' . drush_get_option('package-handler', 'git_drupalorg'),
      'destination' => $site['site.working_directory'],
      'drupal-project-rename' => 'code',
    );
    foreach ($commandline_options as $name => $value) {
      drush_set_option($name, $value);
    }
    if ($this->site['verbose']) {
      $command = 'drush dl ' . implode(' ', $commandline_args) . ' --verbose';
      foreach ($commandline_options as $key => $value) {
        $command .= " --$key=\"$value\"";
      }
      drush_log(dt('Executing: `!command`. ', array('!command' => $command)), 'ok');
    }
    if (!$this->site['simulate'] && !drush_invoke_process('@none', 'dl', $commandline_args, $commandline_options)) {
      throw new \Fetcher\Exception\FetcherException('Database syncronization FAILED!');
    }
  }

}

