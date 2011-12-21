<?php

namespace Ignition\System;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;

class Posix {

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

  public function createSymLink($realLink, $destination) {
    drush_log("Creating a symlink to point from $destination to $realLink");
    return symlink($realLink, $destination);
  }

 /**
  *
  */
  public function ensureDeleted($path) {
    // Note: PHP doesn't have a recursive deletion function, so we just shell out here.
    // Also git sets file permissions that require the -f.
    if (!drush_get_context('DRUSH_SIMULATE')) {
      return drush_shell_exec('rm -rf %s', $path);
    }
    else {
      drush_log(sprintf('rm -rf %s', $path));
    }
  }

  public function removeSite($site_name) {
    //$this->ensureDeleted($path);
  }

}
