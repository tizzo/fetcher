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
  public function ensureFileExists($path, $owning_user = NULL, $owning_group = NULL, $permission = 755) {
    $old_mask = umask(0);
    $path_parts = explode('/', $path);
    $filename = array_pop($path_parts);
    $directory = implode('/', $path_parts);
    $this->ensureFolderExists($directory, $owning_user, $owning_group);
    if (!file_exists($path)) {
      drush_log("Creating file $path");
      if (!touch($path)) {
        // Throw an exception.
      }
      if (!chmod($path, $permission)) {
        // Throw an exception.
      }
    }
  }

  /**
   * Ensure that a symlink exists and points to the location we want.
   *
   * @param $realPath
   *   The path to the real thing we are linking to.
   * @param $link
   *   The path of the desired link.
   * @return
   *   True on success.
   */
  public function ensureSymLink($realPath, $link) {
    $pathParts = explode('/', $link);
    array_pop($pathParts);
    $destinationDirectory = implode('/', $pathParts);
    if (is_dir($destinationDirectory) && !is_link($link)) {
      drush_log("Creating a symlink to point from $link to $realPath");
      if (!symlink($realPath, $link)) {
        // Throw an exception
        throw new \Exception('Link creation failed.');
      }
    }
    else if (!is_dir($destinationDirectory)) {
      throw new \Exception(sprintf('The directory where the symlink is desired (%s) does not exist.', $destinationDirectory));
    }
    else if (readlink($link) != $realPath) {
      throw new \Exception(sprintf('A symlink already exists at %s but it points to %s rather than %s.', $link, readlink($link), $realPath));
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
