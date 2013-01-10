<?php

namespace Fetcher\DBSynchronizer;
use Symfony\Component\Process\Process;

class DrushSqlSync implements DBSynchronizerInterface {

  protected $site = NULL;

  public function __construct(\Fetcher\Site $site) {
    $this->site = $site;
  }

  public function syncDB() {
    // Don't hard code this and rework all of it to work properly with aliases.
    $commandline_options = array(
      '--no-ordered-dump',
      '--yes',
      '--uri=' . $this->site['hostname'],
    );
    if ($this->site['verbose']) {
      $commandline_options[] = '--verbose';
    }
    $commandline_args = array(
      // TODO: Support multisite?
      // TODO: Get this dynamically.
      '@' . $this->site['name'] . '.' . $this->site['environment.remote'],
      '@' . $this->site['name'] . '.local',
    );
    if ($this->site['verbose']) {
      $command = sprintf('drush sql-sync %s %s', implode(' ', $commandline_args), implode(' ', $commandline_options));
      drush_log(dt('Executing: `!command`. ', array('!command' => $command)), 'ok');
    }
    if (!drush_invoke_process($commandline_args[1], 'sql-sync', $commandline_args, $commandline_options)) {
      throw new \Fetcher\Exception\FetcherException('Database syncronization FAILED!');
    }
  }
}
