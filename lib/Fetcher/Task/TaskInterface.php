<?php

namespace Fetcher\Task;
use Symfony\Process;

interface TaskInterface {

  /**
   * Run this task.
   *
   * @param $site
   *   A \Fetcher\Site interface implementing object.
   */
  public function run($site, $arguments = array());

}

