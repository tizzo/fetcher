<?php

namespace Fetcher\Task;

abstract class AbstractTask implements TaskInterface {

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
  public $stacks = array();

  // Any configuration provided to this task.
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
    if (!empty($this->beforeMessage)) {
      $site['log']($this->prepMessage($this->beforeMessage, $site), 'ok');
    }
    $this->performAction($site, $arguments);
    if (!empty($this->afterMessage)) {
      $site['log']($this->prepMessage($this->afterMessage, $site), 'ok');
    }
  }

  /**
   * Run the internal logic for this task.
   */
  abstract function performAction($site, $arguments);

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

  /**
   * Add a stack dependency.
   *
   * @param $stackName
   *    The name of the task stack this task should be a member of.
   * @param $taskName
   *    The name of the task nam of the task this task should relate to.
   * @param $position
   *    Whether to go `before` or `after` the specified task.
   */
  public function addTaskStackDependency($stackName, $taskName = NULL, $position = 'after') {
    if (!in_array($position, array('before', 'after'))) {
      throw new TaskLoaderException('Invalid position specificied.');
    }
    if (!isset($this->stacks[$stackName])) {
      $this->stacks[$stackName] = array();
    }
    if (!is_null($taskName)) {
      $this->stacks[$stackName][$position . 'Task'][] = $taskName;
    }
  }

}
