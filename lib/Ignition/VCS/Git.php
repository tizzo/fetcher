<?php

namespace Ignition\VCS;
use Ignition\Base as Base;

class Git extends Base {

  protected $vcsURL = '';
  protected $codeDirectory = '';

  public function initialCheckout($branch) {
    if (is_null($branch)) {
      $branch = 'master';
    }
    return $this->executeGitCommand('clone %s %s --branch=%s', $this->vcsURL, $this->codeDirectory, $branch);
  }

  public function update($localDirectory) {
    //update = "cd %s; git checkout %s; git pull; git submodule update --init" % (localDirectory, label)
    $this->executeGitCommand('pull', $branch);
  }

  public function checkoutBranch($branch) {
    return $this->checkoutRef($branch);
  }

  public function checkoutTag($tag) {
    return $this->checkoutRef($tag);
  }

  private function checkoutRef($ref) {
    $this->executeGitCommand('--work-tree=%s --git-dir=%s checkout %s', $this->codeDirectory, $this->codeDirectory . '/.git', $branch);
  }

  private function executeGitCommand($command) {

    // Attempt to ramp up the memory limit and execution time
    // to ensure big or slow chekcouts are not interrupted, storing
    // the current values so they may be restored.
    $timeLimit = ini_get('memory_limit');
    ini_set('memory_limit', 0);
    $memoryLimit = ini_get('max_execution_time');
    ini_set('max_execution_time', 0);

    $args = func_get_args();
    // TODO: Allow the git path to be specified?
    $args[0] = 'git ' . $args[0];

    if (drush_get_context('DRUSH_VERBOSE')) {
      $function = 'drush_shell_exec_interactive';
    }
    else {
      $function = 'drush_shell_exec';
    }
    $status = call_user_func_array($function, $args);

    // Restore the memory limit and execution time.
    ini_set('memory_limit', $timeLimit);
    ini_set('max_execution_time', $memoryLimit);

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
