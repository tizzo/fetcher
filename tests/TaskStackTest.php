<?php
require_once "vendor/autoload.php";

// Load domain classes
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
    $taskNames = array_keys($stack->getTasks());
    $this->assertEquals('two', $taskNames[1]);
    $message = 'Task two a comes after its dependency, task two.';
    $this->assertGreaterThan(array_search('two', $taskNames), array_search('two a', $taskNames), $message);

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

    // Ensure if we try to remove a non-existant task an exception is thrown.
    $stack = $this->getSimpleTaskStack();
    try {
      $stack->removeTask('I do not exist');
      $this->assertEquals(FALSE, TRUE, 'An exception is thrown trying to remove a non-existant task');
    }
    catch (Exception $exception) {
      $this->assertInstanceOf('Fetcher\Task\TaskException', $exception);
    }

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

    $stack = new TaskStack('foo');
    try {
      $stack->run($site);
      $this->assertEquals(FALSE, TRUE, 'Empty task stack threw an exception');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf('Fetcher\Task\TaskRunException', $e);
      $this->assertContains('foo', $e->getMessage());
    }
  }
 
  /**
   * Tests task weigthing
   */
  public function testTaskOrderingRun() {
    $stack = new TaskStack('test');
    $three = new Task('three');
    $three->afterTask = 'two';
    $one = new Task('one');
    $one->beforeTask = 'two';
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

