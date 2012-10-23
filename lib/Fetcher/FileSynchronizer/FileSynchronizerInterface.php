<?php

namespace Fetcher\FileSynchronizer;

interface FileSynchronizerInterface {

  /**
   * Move files between remote and local environments.
   *
   * @param $type
   *   The type of drupal files to sync. Accepts the following:
   *     - 'public'
   *     - 'private'
   *     - 'both'
   *   Defaults to both.
   *
   *  @return A single integer between 0 and 3. This works like the numbers on
   *    linux/unix file permissions. The score is the addition of public file
   *    sync success (0 or 1) with private file sync success (0 or 2).
   */
  public function syncFiles($type);

}
