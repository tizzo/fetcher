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

  public function __construct() {
    $this->graph = new DirectedAdjacencyList();
  }

  /**
   * Run this task stack.
   */
  public function run($site, $arguments = array()) {
    if (empty($this->tasks)) {
      throw new TaskRunException('No sub-tasks were added before running the task stack.');
    }
    if (!empty($this->beforeMessage)) {
      $site['log']($this->prepMessage($this->beforeMessage, $site), 'ok');
    }
    foreach ($this->sortTasks() as $name => $task) {
      $task->run($site);
    }
    if (!empty($this->afterMessage)) {
      $site['log']($this->prepMessage($this->afterMessage, $site), 'success');
    }
  }

  /**
   * Add a task to this stack.
   */
  public function addTask(TaskInterface $task) {
    $this->tasks[$task->fetcherTask] = $task;
    $this->graph->addVertex($task);
    // If the task announces that it goes before or after another task, set the
    // dependecy and then reorder the graph here.
    //  TODO: We can't really do ordering until all the tasks have been added.
    /*
    if (!empty($task->beforeTask) && $this->) {
      $this->
      // Here we add c as a dependency of a
      $graph->addDirectedEdge($task, $this->getTask());
    }
    //*/
    return $this;
  }

  /**
   * Sort the tasks on this task stack based on their defined dependencies.
   *
   * @return
   *   A linear array of topologically sorted tasks.
   */
  public function sortTasks() {
    foreach ($this->tasks as $task) {
      if (!empty($task->beforeTask) && !is_null($this->getTask($task->beforeTask))) {
        // Here we add the task as a dependency of the task it wants to
        // run before.
        $this->graph->addDirectedEdge($this->getTask($task->beforeTask), $task);
      }
      if (!empty($task->afterTask) && !is_null($this->getTask($task->afterTask))) {
        // Here we add the task as a dependency of the task it wants to
        // run before.
        $this->graph->addDirectedEdge($task, $this->getTask($task->afterTask));
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
    return $this->sortTasks();
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
