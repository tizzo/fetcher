<?php
/**
 * @file
 * Test the TaskStack class.
 */

require_once "vendor/autoload.php";

use \Fetcher\Task\Task,
  \Fetcher\Task\TaskStack,
  \Fetcher\Site,
  \Fetcher\Task\TaskRunException,
  \Fetcher\Task\TaskException;


class TaskStackTest extends PHPUnit_Framework_TestCase {

  /**
   * Tests TaskStack::addTask().
   */
  public function testAddTask() {
    $stack = new TaskStack('stack');
    $this->assertEquals(0, count($stack->getTasks()));
    $stack->addTask(new Task('foo'));
    $this->assertEquals(1, count($stack->getTasks()));
  }

  /**
   * Tests TaskStack::getTasks().
   */
  public function testGetTasks() {
    $stack = new TaskStack('foo');
    $stack->addTask(new Task('bar'));
    $stack->addTask(new Task('baz'));
    $tasks = $stack->getTasks();
    $this->assertEquals(2, count($tasks));
    $this->assertEquals($tasks['bar']->fetcherTask, 'bar');
    $this->assertEquals($tasks['baz']->fetcherTask, 'baz');
  }

  /**
   * Tests TaskStack::addTaskBefore().
   */
  public function testAddTaskBefore() {

    // Ensure we can add a task in the middle of the stack.
    $stack = $this->getSimpleTaskStack();
    $stack->addBefore('two', new Task('one a'));
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals('one', $taskNames[0]);
    $this->assertEquals('one a', $taskNames[1]);
    $this->assertEquals('two', $taskNames[2]);

    // Ensure we can add a task at the very beginning of the stack.
    $stack = $this->getSimpleTaskStack();
    $stack->addBefore('one', new Task('sub one'));
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals('sub one', $taskNames[0]);
    $this->assertEquals('one', $taskNames[1]);
  }

  /**
   * Tests TaskStack::addAfter().
   */
  public function testAddAfter() {

    // Ensure we can add an item after a task in the middle of the stack.
    $stack = $this->getSimpleTaskStack();
    $stack->addAfter('two', new Task('two a'));
    $this->assertContains('two', $stack->getTaskNames());
    $message = 'Task two a comes after its dependency, task two.';
    $twoPosition = array_search('two', $stack->getTaskNames());
    $twoAPosition = array_search('two a', $stack->getTaskNames());
    $this->assertGreaterThan($twoPosition, $twoAPosition, $message);

    // Ensure we can add an item to the very end of the stack.
    $stack = $this->getSimpleTaskStack();
    $stack->addAfter('three', new Task('three a'));
    $taskNames = array_keys($stack->getTasks());
    $this->assertGreaterThan(array_search('three', $taskNames), array_search('three a', $taskNames));
  }

  /**
   * Tests TaskStack::removeTask().
   */
  public function testRemoveTask() {

    // Ensure we can remove an item from the middle of the task stack.
    $stack = $this->getSimpleTaskStack();
    $stack->removeTask('two');
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals(2, count($taskNames));
    $this->assertEquals('one', $taskNames[0]);
    $this->assertEquals('three', $taskNames[1]);

    // Ensure we can remove an item from the middle of a task stack.
    $stack = $this->getSimpleTaskStack();
    $stack->removeTask('one');
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals(2, count($taskNames));
    $this->assertEquals('two', $taskNames[0]);
    $this->assertEquals('three', $taskNames[1]);

    // Ensure we can remove an item from the end of a task stack.
    $stack = $this->getSimpleTaskStack();
    $stack->removeTask('three');
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals(2, count($taskNames));
    $this->assertEquals('one', $taskNames[0]);
    $this->assertEquals('two', $taskNames[1]);

  }

  /**
   * Tests that removing a nonexistent task throws an exception.
   *
   * @expectedException Fetcher\Task\TaskException
   */
  public function testRemoveNonExistantTaskThrowsException() {
    $stack = $this->getSimpleTaskStack();
    $stack->removeTask('I do not exist');
  }

  /**
   * Run a task stack and ensure that the run works.
   */
  public function testRun() {
    $stack = $this->getSimpleTaskStack();
    $site = new Site();
    foreach ($stack->getTasks() as $task) {
      $task->callable = function($s) use ($site, $task) {
        $site['log']($task->fetcherTask . ' has run');
      };
    }
    $history = array();
    $site['log'] = $site->protect(function($message) use (&$history) {
      $history[] = $message;
    });
    $stack->run($site);
    $this->assertEquals('one has run', $history[0]);
    $this->assertEquals('two has run', $history[1]);
    $this->assertEquals('three has run', $history[2]);
  }

  /**
   * Test that running an empty task stack throws an exception.
   *
   * @expectedException Fetcher\Task\TaskRunException
   */
  public function testRunOnEmptyTaskThrowsException() {
    $stack = new TaskStack('foo');
    $stack->run(new Site());
  }

  /**
   * Tests task weigthing.
   */
  public function testTaskOrderingRun() {
    $stack = new TaskStack('test');
    $three = new Task('three');
    $three->addTaskStackDependency('test', 'two');
    $one = new Task('one');
    $one->addTaskStackDependency('test', 'two', 'before');
    $two = new Task('two');
    $stack
      ->addTask($three)
      ->addTask($one)
      ->addTask($two);
    $site = new Site();
    foreach ($stack->getTasks() as $task) {
      $task->callable = function($s) use ($site, $task) {
        $site['log']($task->fetcherTask . ' has run');
      };
    }
    $history = array();
    $site['log'] = $site->protect(function($message) use (&$history) {
      $history[] = $message;
    });
    $stack->run($site);
    $this->assertEquals('one has run', $history[0]);
    $this->assertEquals('two has run', $history[1]);
    $this->assertEquals('three has run', $history[2]);
  }

  /**
   * Test getting a list of task names in order.
   */
  public function testGetTaskNames() {
    $stack = $this->getSimpleTaskStack();
    $taskNames = $stack->getTaskNames();
    $this->assertEquals('one', $taskNames[0]);
    $this->assertEquals('two', $taskNames[1]);
    $this->assertEquals('three', $taskNames[2]);
  }

  /**
   * Get a configured task stack with 3 tasks.
   */
  private function getSimpleTaskStack() {
    $stack = new TaskStack('test');
    $stack
      ->addTask(new Task('one'))
      ->addTask(new Task('two'))
      ->addTask(new Task('three'));
    return $stack;
  }

}
