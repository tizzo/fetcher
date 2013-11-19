<?php

namespace Fetcher\DBSynchronizer;
use Symfony\Component\Process\Process,
    \Fetcher\Site,
    \Fetcher\Exception\FetcherException;
class DrushSqlSync implements DBSynchronizerInterface {

  protected $site = NULL;

  public function __construct(\Fetcher\Site $site) {
    $this->site = $site;
  }

  public function syncDB($site = NULL) {
    if (is_null($site)) {
      $site = $this->site;
    }
    // Don't hard code this and rework all of it to work properly with aliases.
    $commandline_options = array(
      '--no-ordered-dump',
      '--yes',
      '--uri=' . $site['hostname'],
    );
    if ($site['verbose']) {
      $commandline_options[] = '--verbose';
    }
    $remote = $site->getEnvironment($site['environment.remote']);
    $commandline_args = array(
      $remote['remote-user'] . '@' . $remote['remote-host'] . ':' . $remote['root'] . '#' . $remote['uri'],
      $site['site.code_directory'] . '#' . $site['site'],
    );
    if ($site['verbose']) {
      $command = sprintf('drush sql-sync %s %s', implode(' ', $commandline_args), implode(' ', $commandline_options));
      $site['log'](sprintf('Executing: `%s`. ', $command), 'ok');
      if (!$site['simulate']) {
        $process = $site['process']($command);
        if (!$process->run()) {
          throw new FetcherException('Database syncronization FAILED!');
        }
      }
    }
  }
}
