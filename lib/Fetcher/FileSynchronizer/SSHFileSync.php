<?php

namespace Fetcher\FileSynchronizer;
use Symfony\Component\Process\Process;

class SSHFileSync implements FileSynchronizerInterface {

  protected $site = NULL;

  public function __construct(Pimple $site) {
    $this->site = $site;
  }

  public function syncFiles() {
    $site = $this->site;
    $commandline_options = array('-y');
    if ($site['verbose']) {
      $commandline_options[] = '--verbose';
    }
    var_dump($commandline_options);
    var_dump($site);
    $commandline_args = array(
      // TODO: Support multisite?
      sprintf('@%s.%s:%s', $site['name'], $site['environment'], '%files'),
      sprintf('@%s.local:%s', $site['name'], '%files'),
    );
    //$command = sprintf('drush rsync %s %s', implode(' ', $commandline_options), implode(' ', $commandline_args));
    if ($site['verbose']) {
      drush_log(dt('Executing: `!command`. ', array('!command' => $command)), 'ok');
    }
    //  drush_log(dt('Executing: `!command`. ', array('!command' => $command)), 'ok');
    //$process = $site['process']($command);
    //$process->run(function($type, $buffer) {
    //  if ('err' === $type) {
    //    drush_log('  '.$buffer, 'error');
    //  }
    //  else {
    //    drush_log('  '.$buffer, 'info');
    //  }
    //});
   // if (!$process->isSuccessful()) {
   //   throw new Exception('File synchronization failed');
   // }
    $file_move_success = drush_invoke_process('@' . $site['name'] . '.local', 'rsync', $commandline_args, $commandline_options);
    var_dump($file_move_succees);
  }
}
