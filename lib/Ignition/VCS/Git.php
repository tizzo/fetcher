<?php

namespace Ignition\VCS;
use Ignition\Base as Base;
use Symfony\Component\Process\Process;

class Git extends Base {

  protected $vcsURL = '';
  protected $codeDirectory = '';

  public function initialCheckout($branch = 'master') {
    $this->executeGitCommand('clone %s %s --branch=%s', $this->vcsURL, $this->codeDirectory, $branch);
    if (is_file($this->codeDirectory . '/.gitmodules')) {
      $this->executeGitCommand('--work-tree=%s --git-dir=%s submodule sync', $this->codeDirectory, $this->codeDirectory . '/.git');
      $this->executeGitCommand('--work-tree=%s --git-dir=%s submodule update --init --recursive', $this->codeDirectory, $this->codeDirectory . '/.git');
    }
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

  /**
   * Execute a git command.
   *
   * @param $command
   *   The command to execute without the `git` prefix (e.g. `pull`).
   */
  private function executeGitCommand($command) {

    $args = func_get_args();
    // TODO: Allow the git path to be specified?
    $args[0] = 'git ' . $args[0];
    $command = call_user_func_array('sprintf', $args);
    drush_log('Executing `' . $command . '`.');

    if (drush_get_context('simulate')) {
      return TRUE;
    }

    // Attempt to ramp up the memory limit and execution time
    // to ensure big or slow chekcouts are not interrupted, storing
    // the current values so they may be restored.
    $timeLimit = ini_get('memory_limit');
    ini_set('memory_limit', 0);
    $memoryLimit = ini_get('max_execution_time');
    ini_set('max_execution_time', 0);

    $process = new Process($command, $this->codeDirectory);
    $process->run(function ($type, $buffer) {
      if (drush_get_context('DRUSH_VERBOSE')) {
        if ($type === 'err') {
          drush_log($buffer, 'error');
        } else {
          drush_log($buffer);
        }
      }
    });

    // Restore the memory limit and execution time.
    ini_set('memory_limit', $timeLimit);
    ini_set('max_execution_time', $memoryLimit);

    return $process->isSuccessful();
  }
}
