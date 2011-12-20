<?php

namespace Ignition\System;

// 
class Debian /*extends Posix implements Ignition\System\SystemInterface*/ {

  public function __construct() {
  }

  /** 
   * 
   */
  public function getWebRoot() {
    return '/var/www';
  }

  // TODO: This really should be different for different servers.
  public function getWebUser() {
    return 'www-data';
  }

  public function buildApacheRestart() {
    return '/etc/init.d/apache2 restart';
  }

  /**
   * 
   * TODO: Move to Posix provider.
   */
  public function ensureFolderExists($path, $owning_user = NULL, $owning_group = NULL) {
    $old_mask = umask(0);
    $path_parts = explode('/', $path);
    $success = TRUE;
    $path = '';
    foreach ($path_parts as $part) {
      $path .= $part . '/';
      if ($success && !is_dir($path)) {
        drush_log("Creating folder $path");
        $success = mkdir($path, 0755);
      }
    }
    umask($old_mask);
    return $success;
  }

  /**
   * Ensure that a file exists and is owned by the appropriate user.
   *
   * TODO: Move to Posix provider.
   */
  public function ensureFileExists($path, $owning_user = NULL, $owning_group = NULL) {
    $old_mask = umask(0);
    $path_parts = explode('/', $path);
    $filename = array_pop($path_parts);
    $directory = implode('/', $path_parts);
    $this->ensureFolderExists($directory, $owning_user, $owning_group);
    if (!file_exists($path)) {
      drush_log("Creating file $path");
      touch($path);
      chmod($path, 755);
    }
  }
}
