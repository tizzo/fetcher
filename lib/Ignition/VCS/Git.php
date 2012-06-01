<?php

namespace Ignition\VCS;
use Ignition\Base;
use Symfony\Component\Process\Process;

class Git extends Base {

  protected $vcsURL = '';
  protected $codeDirectory = '';
  protected $container = FALSE;

  public function __construct(\Pimple $container) {
    $this->container = $container;
  }

  public function initialCheckout($branch = 'master') {
    $this->executeGitCommand('clone %s %s --branch=%s --recursive', $this->vcsURL, $this->codeDirectory, $branch);
  }

  public function update($localDirectory) {
    //update = "cd %s; git checkout %s; git pull; git submodule update --init" % (localDirectory, label)
    $this->executeGitCommand('pull --work-tree=%s --git-dir=%s');
    if (is_file($this->codeDirectory . '/.gitmodules')) {
      $oldWD = getcwd();
      chdir($this->codeDirectory);
      $this->executeGitCommand('submodule sync');
      $this->executeGitCommand('submodule update --init --recursive');
      chdir($oldWD);
    }
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

    // By default, allow git to be located automatically within the include path.
    $gitBinary = 'git';
    // If an alternate binary path is specified, use it.
    if (isset($this->container['git binary'])) {
      $gitBinary = $this->container['git binary'];
    }
    $args[0] = $gitBinary . ' ' . $args[0];
    $command = call_user_func_array('sprintf', $args);
    drush_log('Executing `' . $command . '`.');

    // Attempt to ramp up the memory limit and execution time
    // to ensure big or slow chekcouts are not interrupted, storing
    // the current values so they may be restored.
    $timeLimit = ini_get('memory_limit');
    ini_set('memory_limit', 0);
    $memoryLimit = ini_get('max_execution_time');
    ini_set('max_execution_time', 0);

    $process = new Process($command);
    if (!$this->container['simulate']) {
      // Git operations can run long, set our timeout to an hour.
      $process->setTimeout(3600);
      $process->run(function ($type, $buffer) {
        if ('err' === $type) {
          drush_print_prompt('Git Status: '.$buffer, 4);
        } else {
          drush_print_prompt('Git Output: '.$buffer, 4);
        }
      });
    }

    // Restore the memory limit and execution time.
    ini_set('memory_limit', $timeLimit);
    ini_set('max_execution_time', $memoryLimit);

    if (!$process->isSuccessful()) {
      throw new \Exception('Executing Git command failed: `' . $command . '`.  Git responded with: ' . $process->getErrorOutput() . ' ' . $process->getOutput());
    }

    return $process->isSuccessful();
  }
}
