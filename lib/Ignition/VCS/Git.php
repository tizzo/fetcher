<?php

namespace Ignition\VCS;

class Git {

  public function checkout() {
  }

  public function update($localDirectory) {
    //update = "cd %s; git checkout %s; git pull; git submodule update --init" % (localDirectory, label)
  }

  private function executeGitCommand($command) {
    // TODO: Allow the git path to be specified?
    $command = 'git ' . $command;
    if (drush_context('DRUSH_VERBOSE')) {
      $function = 'drush_shell_exec_interactive';
    }
    else {
      $function = 'drush_shell_exec';
    }
    $status = $function($command);
    if ($status == 128) {
      return FALSE;
    }
    else if ($status) {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }
}
