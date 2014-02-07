<?php

namespace Fetcher\Task;
use Symfony\Process;

class ShellTask extends Task implements TaskInterface {

  /**
   * A relative or absolute path to move to before performing the operation.
   */
  public $path = NULL;

  /**
   * The command to shell out and execute.
   */
  public $command = NULL;

  /**
   * The process object after the command has been run.
   */
  protected $process = NULL;

  public function __construct($command, $path = NULL, $taskName = NULL) {
    $this->fetcherTask = $taskName;
    $this->path = $path;
    $this->command = $command;
    $this->beforeMessage = sprintf('Running shell command `%s`...', $command);
  }


  /**
   * Run the build hook.
   */
  function performAction($site, $arguments = array()) {
    $this->process = $process = $site['process']($this->command, $this->path);
    $process->run(function ($type, $buffer) use ($site) {
      foreach (explode(PHP_EOL, trim($buffer)) as $line) {
        if ($type === 'err') {
          $site['log']('    > ' . $line, 'info');
        }
        else {
          $site['log']('    > ' . $line, 'error');
        }
      }
    });
    if (!$process->isSuccessful()) {
      throw new TaskRunException(\sprintf('Executing `%s` failed.', $this->command));
    }
  }

  /**
   * Get the output from the command.
   */
  public function getOutput() {
    if (empty($this->process)) {
      throw new \Exception('Process was not run before output was requested.');
    }
    return $this->process->getOutput();
  }
}

