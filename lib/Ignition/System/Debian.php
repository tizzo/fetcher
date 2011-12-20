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
  public function ensureFolderExists($path, $owning_user = FALSE, $owning_group = FALSE) {
    $old_mask = umask(0);
    $path_parts = explode('/', $path);
    $success = TRUE;
    $path = '';
    foreach ($path_parts as $part) {
      $path .= '/' . $part;
      if ($success && !is_dir($path)) {
        drush_log("Creating folder $path");
        $success = mkdir($path);
      }
    }
    umask($old_mask);
    return $success;
  }

  /**
   *
   */
  public function ensureFileExists($path, $owning_user = FALSE, $owning_group = FALSE) {
    $old_mask = umask(0);
    $path_parts = explode('/', $path);
    $filename = array_pop($path_parts);
    $directory = implode('/', $path_parts);
    $this->ensureFolderExists($directory, $owning_user, $owning_group);
    touch($path);
    chmod($path, 755);
  }
}
