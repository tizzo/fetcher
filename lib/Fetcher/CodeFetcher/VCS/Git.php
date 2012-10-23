<?php

namespace Fetcher\CodeFetcher\VCS;

class Git implements \Fetcher\CodeFetcher\SetupInterface, \Fetcher\CodeFetcher\UpdateInterface {

  protected $site = FALSE;

  public function __construct(\Pimple $site) {
    $this->site = $site;
    // If we do not have a default branch, set it to master.
    $config = $site['code_fetcher.config'];
    if (!isset($config['branch'])) {
      $config['branch'] = 'master';
    }
    if (!isset($site['git binary'])) {
      $site['git binary'] = 'git';
    }
    $site['code_fetcher.config'] = $config;
  }

  public function setup() {
    $site = $this->site;
    $this->executeGitCommand('clone %s %s --branch=%s --recursive', $this->site['code_fetcher.config']['url'], $this->site['site.code_directory'], $this->site['code_fetcher.config']['branch']);
  }

  public function update() {
    $site = $this->site;
    // If we have a branch set, ensure that we're on it.
    if (isset($site['code_fetcher.config']['branch'])) {
      $this->executeGitCommand('--work-tree=%s --git-dir=%s checkout %s', $site['site.code_directory'], $site['site.code_directory'] . '/.git', $site['code_fetcher.config']['branch']);
    }
    // Pull in the latest code.
    $this->executeGitCommand('--work-tree=%s fetch', $site['site.code_directory'] . '/.git');
    $this->executeGitCommand('--work-tree=%s --git-dir=%s rebase %s', $site['site.code_directory'], $site['site.code_directory'] . '/.git', $site['code_fetcher.config']['branch']);
    // If we have submodules update them.
    if (is_file($this->codeDirectory . '/.gitmodules')) {
      $oldWD = getcwd();
      chdir($this->codeDirectory);
      $this->executeGitCommand('submodule sync');
      $this->executeGitCommand('submodule update --init --recursive');
      chdir($oldWD);
    }
  }

  /**
   * Execute a git command.
   *
   * @param $command
   *   The command to execute without the `git` prefix (e.g. `pull`).
   */
  private function executeGitCommand($command) {

    $args = func_get_args();
    $site = $this->site;

    $args[0] = $site['git binary'] . ' ' . $args[0];
    $command = call_user_func_array('sprintf', $args);
    $site['log']('Executing `' . $command . '`.');

    // Attempt to ramp up the memory limit and execution time
    // to ensure big or slow chekcouts are not interrupted, storing
    // the current values so they may be restored.
    $timeLimit = ini_get('memory_limit');
    ini_set('memory_limit', 0);
    $memoryLimit = ini_get('max_execution_time');
    ini_set('max_execution_time', 0);

    $process = $site['process']($command);
    if (!$site['simulate']) {
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
