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
      $this->executeGitCommand('checkout %s', $site['code_fetcher.config']['branch']);
    }
    // Pull in the latest code.
    $this->executeGitCommand('pull');
    // If we have submodules update them.
    if (is_file($site['site.code_directory'] . '/.gitmodules')) {
      $this->executeGitCommand('submodule sync');
      $this->executeGitCommand('submodule update --init --recursive');
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

    $process = $site['process']($command);
    $process->setWorkingDirectory($site['site.code_directory']);
    if (!$site['simulate']) {
      // Git operations can run long, set our timeout to an hour.
      $process->setTimeout(3600);
      $std_err = fopen('php://stderr', 'w+');
      $std_out = fopen('php://stdout', 'w+');
      $process->run(function ($type, $buffer) {
        if ($type === 'err') {
          fwrite($std_err, '  ' . $command . ': ' . $buffer);
        }
        else {
          fwrite($std_out, '  ' . $command . ': ' . $buffer);
        }
      });

      if (!$process->isSuccessful()) {
        throw new \Exception('Executing Git command failed: `' . $command . '`.  Git responded with: ' . $process->getErrorOutput() . ' ' . $process->getOutput());
      }
    }

    return $process->isSuccessful();
  }
}
