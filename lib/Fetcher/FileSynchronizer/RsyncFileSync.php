<?php

namespace Fetcher\FileSynchronizer;
use Symfony\Component\Process\Process;

class RsyncFileSync implements FileSynchronizerInterface {

  protected $site = NULL;

  public function __construct(Pimple $site) {
    $this->site = $site;
    if (!isset($site['rsync-binary'])) {
      $site['rsync-binary'] = 'rsync';
    }
  }

  private function generateSyncPath($root, $files) {
    if (strpos($files, '/') === 0) {
      // If the files dir is absolute make it the full path...
      $full_path = $files . '/.';
    }
    else {
      // otherwise append it to the root.
      $full_path = $root . '/' .  $files . '/.';
    }
    return $full_path;
  }

  private function generateAndRunSyncCommand($site, $local_path, $remote_path, $type) {
    $site['log'](dt('Found @files files directory -- attempting sync:', array('@files' => $type)), 'ok');
    $command = sprintf('%s -r %s@%s:%s %s',
      $site['rsync-binary'],
      $site['remote-user'],
      $site['remote-host'],
      $remote_path,
      $local_path
    );

    if ($site['verbose']) {
      $site['log'](dt('Executing: `!command`. ', array('!command' => $command)), 'ok');
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
      $args = array('@files' => $type, '!eol' => PHP_EOL, '@error' => $process->getErrorOutput());
      throw new \Exception(dt('File synchronization failed for @files: !eol @error', $args));
    }
    $site['log']($process->getOutput(), 'ok');
    return TRUE;
  }

  public function syncFiles($type = 'both') {
    // Set flags for what files to sync.
    $sync_public = TRUE;
    $sync_private = TRUE;
    if ($type == 'public') {
      $sync_private = FALSE;
    }
    if ($type == 'private') {
      $sync_public = FALSE;
    }

    $private_synced = FALSE;
    $public_synced = FALSE;
    $site = $this->site;
    $commandline_options = array('backend');
    if ($site['verbose']) {
      $commandline_options[] = '--verbose';
    }
    $commandline_args = array(
      // TODO: Support multisite?
      sprintf('@%s.%s:%s', $site['name'], $site['environment.remote'], '%files'),
      sprintf('@%s.local:%s', $site['name'], '%files'),
    );
    $args = array(
      'File directory path',
      'Private file directory path',
      'Drupal root',
    );
    $site['log'](dt('Remote file path information:'), 'ok');
    $remote_status_result = drush_invoke_process('@' . $site['name'] . '.' . $site['environment.remote'], 'status', $args, $commandline_options);
    $remote_root_path = $remote_status_result['object']['Drupal root'];
    $remote_public_files = $remote_status_result['object']['File directory path'];
    $remote_private_files = !empty($remote_status_result['object']['Private file directory path']) ? $remote_status_result['object']['Private file directory path'] : '';

    $site['log'](dt('Local file path information:'), 'ok');
    $local_status_result = drush_invoke_process('@' . $site['name'] . '.local', 'status', $args, $commandline_options);
    $local_root_path = $local_status_result['object']['Drupal root'];
    $local_public_files = $local_status_result['object']['File directory path'];
    $local_private_files = !empty($local_status_result['object']['Private file directory path']) ? $local_status_result['object']['Private file directory path'] : '';

    if (!empty($remote_public_files) && !empty($local_public_files) && $sync_public) {
      // This should create an rsync command to run via process. It should look
      // something like 'rsync -r user@some.server:/path/to/files /path/to/files'
      $remote_rsync_path = $this->generateSyncPath($remote_root_path, $remote_public_files);
      $local_rsync_path = $this->generateSyncPath($local_root_path, $local_public_files);
      $public_synced = $this->generateAndRunSyncCommand($site, $local_rsync_path, $remote_rsync_path, 'public');
    }

    // Handle the private files if they exist.
    if (!empty($remote_private_files) && !empty($local_private_files) && $sync_private) {
      $local_rsync_path = $this->generateSyncPath($local_root_path, $local_private_files);
      $remote_rsync_path = $this->generateSyncPath($remote_root_path, $remote_private_files);
      $private_synced = $this->generateAndRunSyncCommand($site, $local_rsync_path, $remote_rsync_path, 'private');
    }
    return (int) $public_synced + ((int) $private_synced) * 2;
  }
}
