<?php

namespace Ignition\DBSynchronizer;
use Symfony\Component\Process\Process;

class DrushSqlSync implements DBSynchronizerInterface {

  protected $container = NULL;

  public function __construct(Pimple $container) {
    $this->container = $container;
  }

  public function syncDB(array $alias) {
    // Don't hard code this and rework all of it to work properly with aliases.
    drush_log(dt('Attempting to sync database from remote.'));
    $commandline_options = array(
      'no-ordered-dump',
      'verbose',
      'yes',
    );
    if (drush_get_context('verbose')) {
      $commandline_options['verbose'] = TRUE;
    }
    $conf = drush_ignition_get_service_container();
    $commandline_args = array(
      // TODO: Support multisite?
      // TODO: Get this dynamically.
      '@' . $alias->name . '.live',
      '@' . $alias->name . '.local',
    );
    $options_text = array();
    foreach ($commandline_options as $name => $value) {
      if (!is_numeric($name)) {
        $value = '--' . $name . '=' . escapeshellarg($value);
      }
      else {
        $value = '--' . $value;
      }
      $options_text[] = $value;
    }
    $command = 'drush sql-sync ' . implode(' ', $commandline_args) . ' ' . implode(' ', $options_text);
    drush_log(dt('Executing: `!command`. ', array('!command' => $command)), 'ok');
    /*
    if (!drush_invoke_process($commandline_args[1], 'sql-sync', $commandline_args, $commandline_options)) {
      throw new \Ignition\Exception\IgnitionException('Database syncronization FAILED!');
    }
    */

    if (!$this->container['simulate']) {
      $process = new Process($command, $this->codeDirectory);
      $process->setTimeout(600);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new Exception('Database synchronization failed!');
      }
    }
  }
}
