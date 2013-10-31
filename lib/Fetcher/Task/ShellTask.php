<?php

namespace Fetcher\Task;
use Symfony\Process;

class ShellTask extends Task implements TaskInterface {

  public $path;

  /**
   * Run the build hook.
   */
  public function run($site, $arguments = array()) {
    // If we have a relative path to switch to it.
    if (isset($path)) {
      $startingDir = getcwd();
      cwd($path);
    }
    $site['log'] = 
    parent::run();
    // If we had a starting directory, be a good citizen and switch back.
    if (!empty($startingDir)) {
      cwd($startingDir);
    }
  }
}
