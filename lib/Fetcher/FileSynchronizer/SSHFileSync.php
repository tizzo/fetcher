<?php

namespace Fetcher\FileSynchronizer;
use Symfony\Component\Process\Process;

class SSHFileSync implements FileSynchronizerInterface {

  protected $site = NULL;

  public function __construct(Pimple $site) {
    $this->site = $site;
    if (!isset($site['rsync-binary'])) {
      $site['rsync-binary'] = 'rsync';
    }
  }

  private function generateSyncPath($root, $files) {
    if (strpos('/', $files) === 0) {
      // If the files dir is absolute make it the full path...
      $full_path = $files . '/.';
    }
    else {
      // otherwise append it to the root.
      $full_path = $root . '/' .  $files . '/.';
    }
    return $full_path;
  }

  public function syncFiles() {
    $synced = FALSE;
    $site = $this->site;
    $commandline_options = array('backend');
    if ($site['verbose']) {
      $commandline_options[] = '--verbose';
    }
    $commandline_args = array(
      // TODO: Support multisite?
      sprintf('@%s.%s:%s', $site['name'], $site['environment'], '%files'),
      sprintf('@%s.local:%s', $site['name'], '%files'),
    );
    $args = array(
      'File directory path',
      'Private file directory path',
      'Drupal root',
    );
    drush_log(dt('Remote file path information:'), 'ok');
    $remote_status_result = drush_invoke_process('@' . $site['name'] . '.' . $site['environment'], 'status', $args, $commandline_options);
    $remote_root_path = $remote_status_result['object']['Drupal root'];
    $remote_public_files = $remote_status_result['object']['File directory path'];
    $remote_private_files = !empty($remote_status_result['object']['Private file directory path']) ? $remote_status_result['object']['Private file directory path'] : '';
    $remote_rsync_path = $this->generateSyncPath($remote_root_path, $remote_public_files);

    drush_log(dt('Local file path information:'), 'ok');
    $local_status_result = drush_invoke_process('@' . $site['name'] . '.local', 'status', $args, $commandline_options);
    $local_root_path = $local_status_result['object']['Drupal root'];
    $local_public_files = $local_status_result['object']['File directory path'];
    $local_private_files = !empty($local_status_result['object']['Private file directory path']) ? $local_status_result['object']['Private file directory path'] : '';
    $local_rsync_path = $this->generateSyncPath($local_root_path, $local_public_files);

    if (!empty($remote_public_files) && !empty($local_public_files)) {
      // This should create an rsync command to run via process. It should look
      // something like 'rsync -r user@some.server:/path/to/files /path/to/files'
      $command = sprintf('%s -r %s@%s:%s %s',
        $site['rsync-binary'],
        $site['remote-user'],
        $site['remote-host'],
        $remote_rsync_path,
        $local_rsync_path
      );

      if ($site['verbose']) {
        drush_log(dt('Executing: `!command`. ', array('!command' => $command)), 'ok');
      }

      $process = $site['process']($command);
      $process->setTimeout(null);
      $process->run();
      /*
      // It would be nice to do something like this but it just doesn't seem
      //to work.
      $process->run(function ($type, $buffer) {
        if ('err' === $type) {
          drush_log('ERR > ' . $buffer, 'ok');
        }
        else {
          drush_log('OUT > ' . $buffer, 'ok');
        }
      });
      */
      if (!$process->isSuccessful()) {
        throw new \Exception('Public file synchronization failed');
      }
      $synced = TRUE;
      drush_log($process->getOutput(), 'ok');
    }

    // Handle the private files if they exist.
    if (!empty($remote_private_files) && !empty($local_private_files)) {
      $local_rsync_path = $this->generateSyncPath($local_root_path, $local_public_files);
      $remote_rsync_path = $this->generateSyncPath($remote_root_path, $remote_public_files);

      drush_log(dt('Found private files directory -- attempting sync:'), 'ok');
      $command = sprintf('%s -r %s@%s:%s %s',
        $site['rsync-binary'],
        $site['remote-user'],
        $site['remote-host'],
        $remote_rsync_path,
        $local_rsynch_path
      );

      if ($site['verbose']) {
        drush_log(dt('Executing: `!command`. ', array('!command' => $command)), 'ok');
      }

      $process = $site['process']($command);
      $process->setTimeout(null);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new \Exception('Public file synchronization failed');
      }
      drush_log($process->getOutput(), 'ok');
    }
    return $synced;
  }
}
