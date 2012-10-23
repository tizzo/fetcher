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
   */
  public function syncFiles($type);

}
