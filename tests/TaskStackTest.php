<?php
require_once "vendor/autoload.php";

// Load domain classes
use \Fetcher\Task\Task,
  \Fetcher\Task\TaskStack,
  \Fetcher\Site,
  \Fetcher\Task\TaskRunException;


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
    $bar = new Task('bar');
    $stack->addTask($bar);
    $stack->addTask(new Task('baz'));
    $tasks = $stack->getTasks();
    $this->assertEquals(count($tasks), 2);
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
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals('two', $taskNames[1]);
    $this->assertEquals('two a', $taskNames[2]);

    // Ensure we can add an item to the very end of the stack.
    $stack = $this->getSimpleTaskStack();
    $stack->addAfter('three', new Task('three a'));
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals('three', $taskNames[2]);
    $this->assertEquals('three a', $taskNames[3]);
  }

  /**
   * Tests TaskStack::remove().
   */
  public function testRemove() {
    // Ensure we can remove an item from the middle of the task stack.
    $stack = $this->getSimpleTaskStack();
    $stack->remove('two');
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals(2, count($taskNames));
    $this->assertEquals('one', $taskNames[0]);
    $this->assertEquals('three', $taskNames[1]);
    // Ensure we can remove an item from the middle of a task stack.
    $stack = $this->getSimpleTaskStack();
    $stack->remove('one');
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals(2, count($taskNames));
    $this->assertEquals('two', $taskNames[0]);
    $this->assertEquals('three', $taskNames[1]);
    // Ensure we can remove an item from the end of a task stack.
    $stack = $this->getSimpleTaskStack();
    $stack->remove('three');
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals(2, count($taskNames));
    $this->assertEquals('one', $taskNames[0]);
    $this->assertEquals('two', $taskNames[1]);
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

