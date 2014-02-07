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

  /**
   * Perform whatever actions necessary for the calling code.
   *
   * @param $site
   *   A \Fetcher\Site interface implementing object.
   * @param $arguments
   *   An optional array of arguments to pass to the task.
   */
  function performAction($site, $arguments);

}

