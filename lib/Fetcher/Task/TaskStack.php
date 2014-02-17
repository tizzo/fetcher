<?php

namespace Fetcher\Task;
use Symfony\Process;
use Gliph\Graph\DirectedAdjacencyList;
use Gliph\Traversal\DepthFirst;

require_once "vendor/autoload.php";

class TaskStack extends Task implements TaskInterface {

  // An array of subtasks.
  public $tasks = array();

  // The gliph dependency graph.
  private $graph = null;

  // The vertex to start the sort with, note this is generally the *last*
  // item that needs to run.
  private $startTask = null;

  public function __construct($name) {
    parent::__construct($name);
    $this->graph = new DirectedAdjacencyList();
  }

  /**
   * Run the tasks in this task stack.
   */
  function performAction($site, $arguments = array()) {
    if (empty($this->tasks)) {
      throw new TaskRunException(sprintf('No sub-tasks were added before running the task stack "%s".', $this->fetcherTask));
    }
    foreach ($this->sortTasks() as $name => $task) {
      $task->run($site);
    }
  }

  /**
   * Add a task to this stack.
   */
  public function addTask(TaskInterface $task) {
    // By default we sort by the last task that gets added that we know does not
    // come before something else.
    if (empty($task->beforeTask)) {
      $this->startTask = $task;
    }
    if (count($this->tasks) == 0) {
      $this->graph->addVertex($task);
    }
    else if (empty($task->beforeTask) && empty($task->afterTask)) {
      $this->addAfter(end($this->tasks)->fetcherTask, $task);
    }
    else {
      $this->graph->addVertex($task);
    }
    $this->tasks[$task->fetcherTask] = $task;

    return $this;
  }

  /**
   * Sort the tasks on this task stack based on their defined dependencies.
   *
   * @return
   *   A linear array of topologically sorted tasks.
   */
  public function sortTasks() {
    if (empty($this->tasks)) {
      return array();
    }
    foreach ($this->tasks as $task) {
      if (!empty($task->beforeTask) && !is_null($this->getTask($task->beforeTask))) {
        // Here we add the task as a dependency of the task it wants to
        // run before.
        $this->addBefore($task->beforeTask, $task);
      }
      if (!empty($task->afterTask) && !is_null($this->getTask($task->afterTask))) {
        // Here we add the task as a dependency of the task it wants to
        // run before.
        $this->addAfter($task->afterTask, $task);
      }
    }
    $list = array();
    foreach (DepthFirst::toposort($this->graph) as $task) {
      $list[$task->fetcherTask] = $task;
    }
    return $list;
  }

  /**
   * Get a task that is on this task stack by name.
   *
   * @param $taskName
   *   The string representing the name of the task on this stack.
   */
  public function getTask($taskName) {
    if (empty($this->tasks[$taskName])) {
      return NULL;
    }
    return $this->tasks[$taskName];
  }

  /**
   * Get all tasks in this stack.
   *
   * @return
   *   An array of tasks.
   */
  public function getTasks() {
    if (count($this->tasks)) {
      return $this->sortTasks();
    }
    else {
      return array();
    }
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
    if (is_null($this->getTask($itemName))) {
      return FALSE;
    }
    $this->graph->addDirectedEdge($this->getTask($itemName), $task);
    return $this;
  }

  /**
   * Add a subtask after an existing task.
   *
   * @param $name
   *    The task to add after.
   * @param $task
   *    A \Fetcher\TaskInterface implementing object. 
   */
  // TODO: Reimplement this as a manipulation of the dependency graph.
  public function addAfter($itemName, $task) {
    if (is_null($this->getTask($itemName))) {
      return FALSE;
    }
    $this->graph->addDirectedEdge($task, $this->getTask($itemName));
    return $this;
  }

  /**
   * Remove an existing task from the stack.
   *
   * @param $name
   *    The task to remove.
   */
  public function removeTask($itemName) {
    if (is_null($this->getTask($itemName))) {
      throw new TaskException(sprintf('Trying to remove non-existant task "%s".', $itemName));
    }
    $this->graph->removeVertex($this->getTask($itemName));
    $position = array_search($itemName, array_keys($this->tasks));
    array_splice($this->tasks, $position, 1);
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

}
