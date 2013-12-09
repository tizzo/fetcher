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

  /**
   * Drops the database before the import.
   */
  public function dropTables($site) {
    $commandline_options = array(
      '--yes',
    );
    if ($site['verbose']) {
      $commandline_options[] = '--verbose';
    }
    $commandline_args = array(
      $site['site.code_directory'] . '#' . $site['site'],
    );
    $dropCommand = sprintf('drush sql-drop %s %s', implode(' ', $commandline_options), implode(' ', $commandline_args));
    $site['log'](sprintf('Executing: `%s`. ', $dropCommand), 'info');
    if (!$site['simulate']) {
      $process = $site['process']($dropCommand);
      // According to the process docs, this should be NULL for unlimited.
      $process->setTimeout(NULL);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new FetcherException(sprintf('Dropping old database `%s` FAILED!', $site['database.database']));
      }
    }
  }

  public function syncDB($site = NULL) {
    if (is_null($site)) {
      $site = $this->site;
    }

    // Drop the database before import.
    $this->dropTables($site);

    // Don't hard code this and rework all of it to work properly with aliases.
    $commandline_options = array(
      '--no-ordered-dump',
      '--yes',
    );
    if ($site['verbose']) {
      $commandline_options[] = '--verbose';
    }
    $remote = $site->getEnvironment($site['environment.remote']);
    $localAlias = $site['site.code_directory'] . '#' . $site['site'];
    $commandline_args = array(
      $remote['remote-user'] . '@' . $remote['remote-host'] . ':' . $remote['root'] . '#' . $remote['uri'],
      $localAlias,
    );
    $syncCommand = sprintf('drush sql-sync %s %s', implode(' ', $commandline_args), implode(' ', $commandline_options));
    $site['log'](sprintf('Executing: `%s`. ', $syncCommand), 'info');
    if (!$site['simulate']) {
      $process = $site['process']($syncCommand);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new FetcherException('Database syncronization FAILED!');
      }
    }
  }
}
