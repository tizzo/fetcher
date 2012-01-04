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

  public function ensureSymLink($realPath, $destination) {
    $pathParts = explode('/', $destination);
    array_pop($pathParts);
    $destinationDirectory = implode('/', $pathParts);
    if (is_dir($destinationDirectory) && !is_link($destination)) {
      drush_log("Creating a symlink to point from $destination to $realPath");
      return symlink($realPath, $destination);
    }
    else if (!is_dir($destinationDirectory)) {
      drush_log(dt('The directory where the symlink is desired (!path) does not exist.', array('!path' => $destinationDirectory)), 'error');
      return FALSE;
    }
    else if (readlink($destination) != $realPath) {
      $error = 'A symlink already exists at !destination but it points to !current rather than !desired.';
      $tokens = array('!path' => $destination, '!current' => readlink($destination), '!desired' => $realPath);
      drush_log(dt($error, $tokens, 'error'));
    }
    else {
      return TRUE;
    }
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

  /**
   * Write a file to disk.
   *
   * @param $path
   *   A string representing the absolute path on disk.
   * @param $content
   *   The content to write into the file.
   * @return
   *   Boolean, the result of the file write.
   */
  public function writeFile($path, $content) {
    if (is_dir($path)) {
      drush_log("Writing file to $path");
      return file_put_contents($path, $content);
    }
    else {
      return FALSE;
    }
  }

  public function getUserHomeFolder() {
    $user = posix_getpwuid(getmyuid());
    return $user['dir'];
  }

}
