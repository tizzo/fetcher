<?php

namespace Fetcher\Task;
use Symfony\Process;

class Task implements TaskInterface {

  // The machine name of this task.
  public $fetcherTask = NULL;

  // The description for this task for the task list.
  public $description = NULL;

  // A log message to print before running the task.
  public $beforeMessage = NULL;

  // A log message to print after the callable has been run successfully.
  public $afterMessage = NULL;

  // A log message to print after the callable has been run successfully.
  public $callable = NULL;

  // Any defined task stacks to add this task to.
  public $task_stack = array();

  public $beforeTask = array();

  public $afterTask = array();

  public $arguments = array();

  /**
   * Constructor.
   *
   * @param $fetcher_task
   *   The machine name for this task.
   */
  public function __construct($fetcherTask = NULL) {
    $this->fetcherTask = $fetcherTask;
  }

  /**
   * Run this task.
   */
  public function run($site, $arguments = array()) {
    if (empty($this->callable)) {
      throw new TaskRunException('No callable was assigned to the task before running.');
    }
    if (!empty($this->beforeMessage)) {
      $site['log']($this->prepMessage($this->beforeMessage, $site));
    }
    \call_user_func_array($this->callable, array($site) + $arguments);
    if (!empty($this->afterMessage)) {
      $site['log']($this->prepMessage($this->afterMessage, $site));
    }
  }

  /**
   * Prepare log messages by substituting 
   *
   * Log messages can use data from any config key if the log message uses the format
   * [[array_key]].
   *
   * @param $string
   *   The string on which to perform substitutions.
   * @param $values
   *   An array or oject implementing array access.
   *   Usually a \Fetcher\Site object.
   */
  public function prepMessage($string, $values) {
    $callback = function($matches) use ($values) {
      if (isset($values[$matches[1]])) {
        return $values[$matches[1]];
      }
    };
    $pattern = '/\[\[(.*)\]\]/U';
    return \preg_replace_callback($pattern, $callback, $string);
  }
}
