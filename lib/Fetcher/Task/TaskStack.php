<?php

namespace Fetcher\Task;
use Symfony\Process;

class TaskStack extends Task implements TaskInterface {

  // An array of subtasks.
  public $tasks = array();

  /**
   * Run this task stack.
   */
  public function run($site, $arguments = array()) {
    if (empty($this->tasks)) {
      throw new TaskRunException('No sub-tasks were added before running the task stack.');
    }
    if (!empty($this->beforeMessage)) {
      $site['log']($this->prepMessage($this->beforeMessage, $site));
    }
    foreach ($this->tasks as $name => $task) {
      $task->run();
    }
    if (!empty($this->afterMessage)) {
      $site['log']($this->prepMessage($this->afterMessage, $site));
    }
  }

  /**
   * Add a task to this stack.
   */
  public function addTask(TaskInterface $task) {
    $this->tasks[$task->fetcherTask] = $task;
    return $this;
  }

  public function getTasks() {
    return $this->tasks;
  }

  /**
   * Add a subtask before an existing task.
   *
   * @param $name
   *    The task to add before.
   * @param $task
   *    A \Fetcher\TaskInterface implementing object. 
   * @return
   *    The task stack itself.
   */
  public function addBefore($itemName, $task) {
    if (!isset($this->tasks[$itemName])) {
      return FALSE;
    }
    $position = array_search($itemName, array_keys($this->tasks));
    $this->taskSplice($task, $position);
    return $this;
  }

  /**
   * Splice a new task into the existing array.
   *
   * @param $taskToAdd
   *   The \Fetcher\Task\TaskInterface task to add.
   * @param $position
   *   The position in the stack at which to add the task.
   */
  private function taskSplice($taskToAdd, $position) {
    $oldArray = $this->tasks;
    $oldSectionOne = array_slice($oldArray, 0, $position, true);
    $newSection = array($taskToAdd->fetcherTask => $taskToAdd);
    $oldSectionTwo = array_slice($oldArray, $position, NULL, true);
    $this->tasks = $oldSectionOne + $newSection + $oldSectionTwo;
  }

  /**
   * Add a subtask after an existing task.
   *
   * @param $name
   *    The task to add after.
   * @param $task
   *    A \Fetcher\TaskInterface implementing object. 
   */
  public function addTaskAfter($itemName, $task) {
    if (!isset($this->tasks[$itemName])) {
      return FALSE;
    }
    $position = array_search($itemName, array_keys($this->tasks));
    $this->taskSplice($task, $position + 1);
    return $this;

  }
}
