<?php
require_once "vendor/autoload.php";

// Load domain classes
use \Fetcher\Task\TaskLoader,
    \Fetcher\Task\TaskLoaderException,
    \Fetcher\Task\Task,
    \Fetcher\Task\TaskStack,
    \Fetcher\Site;

// Load test fixture classes.
use \Fetcher\Tests\Fixtures\Tasks\TaskAnnotation,
  \Fetcher\Tests\Fixtures\Tasks\TaskAnnotationError;


class TaskLoaderTest extends PHPUnit_Framework_TestCase {

  /**
   * Get a site object.
   */
  public function getSite(&$history = array()) {
    $site = new Site();
    $site['log'] = $site->protect(function ($message) use (&$history) {
      $history[] = $message;
    });
    return $site;
  }

  /**
   * Test the scanObject() method.
   */
  public function testScanObject() {
    $loader = new TaskLoader();
    $annotatedObject = new TaskAnnotation();
    $tasks = $loader->scanObject($annotatedObject);
    $this->assertNotEmpty($tasks);
    $task = array_pop($tasks);
    $this->assertEquals($task->description, 'Provides a sample task for parsing.');
    $this->assertEquals($task->fetcherTask, 'some_task_name');
    $this->assertEquals($task->beforeMessage, 'We are about to run a task.');
    $this->assertEquals($task->afterMessage, 'We have just run a task.');
    $history = array();
    $site = $this->getSite($history); 
    $task->run($site);
    $this->assertEquals($history[0], 'We are about to run a task.');
    $this->assertEquals($history[1], 'We have just run a task.');
    //$this->assertContains('foo', $task->beforeTask);
    //$this->assertContains('bar', $task->beforeTask);
    //$this->assertContains('baz', $task->afterTask);
  }

  /**
   * Test scanning a class with a bad definition
   */
  public function testScanBadObjectThrowsExceptions() {
    $loader = new TaskLoader();
    $annotatedObject = new TaskAnnotationError();
    try {
      $loader->scanObject($annotatedObject);
      // If we don't throw an exception in the line above something went wrong.
      throw new \Exception('Scanning the TaskAnnotationError class should have throw a TaskLoaderException.');
    }
    catch (TaskLoaderException $exception) {
      $this->assertInstanceOf('\Fetcher\Task\TaskLoaderException', $exception);
    }
  }

  /**
   * Test the scanAllUserSpaceFunctions method.
   *
   * This method also tests the scanFunctions() method.
   */
  public function testScanUserSpaceFunctions() {
    $loader = new TaskLoader();
    $path = __DIR__ . '/../lib/Fetcher/Tests/Fixtures/Tasks/userSpaceFunctions.php';
    $tasks = $loader->scanAllUserSpaceFunctions();
    $this->assertEquals(0, count($tasks));
    require $path;
    $tasks = $loader->scanAllUserSpaceFunctions();
    $this->assertEquals(1, count($tasks));
    $task = array_pop($tasks);
    $site = $this->getSite($history);
    $task->run($site);
    $this->assertEquals(TRUE, $site['user_space_task_ran']);
  }

  /**
   * Test the scanFunctionsInFile method.
   * This method also tests the scanFunctions() method.
   */
  public function testScanFunctionsInFile() {
    $loader = new TaskLoader();
    $path = __DIR__ . '/../lib/Fetcher/Tests/Fixtures/Tasks/taskFunctions.php';
    $tasks = $loader->scanFunctionsInFile($path);
    $this->assertEquals(1, count($tasks), 'The correct number of tasks were detected and loaded.');
    $task = array_pop($tasks);
    $this->assertEquals('fetcher_task_annotated_function', $task->callable);
    $this->assertEquals('some_function', $task->fetcherTask);
    $this->assertEquals('This does some stuff', $task->description);
    $this->assertEquals('The stuff it does is awesome.', $task->afterMessage);
    $site = $this->getSite();
    $task->run($site);
    $this->assertTrue($site['fetcher_task_annotated_function_ran'], 'The annotated function was properly added as the task\'s callable.');
    try {
      $loader->scanFunctionsInFile('NONEXISTANTPATH');
      $this->assertEquals(FALSE, TRUE, 'Loader failed to throw an exception when loading a non-existant path.');
    }
    catch (\Exception $exception) {
      $this->assertInstanceOf('\Fetcher\Task\TaskLoaderException', $exception);
    }
  }

  /**
   * Create a new task stack from an annotation.
   */
  public function testCreateTaskStackFromAnnotation() {
    $loader = new TaskLoader();
    $path = __DIR__ . '/../lib/Fetcher/Tests/Fixtures/Tasks/TaskStackAnnotation.php';
    $tasks = $loader->scanFunctionsInFile($path);
    $tasks = $loader->scanClass('\Fetcher\Tests\Fixtures\Tasks\TaskStackAnnotation');
    $this->assertContains('first_stack_1', array_keys($tasks));
    $this->assertContains('first_stack_2', array_keys($tasks));
    $this->assertContains('test_stack_1', array_keys($tasks));
    $first = $tasks['first_stack_1'];
    $this->assertContains('test_stack_1', array_keys($tasks));
    $this->assertEquals(3, count($tasks));
    $stack = $tasks['test_stack_1'];
    $this->assertContains('first_stack_1', $stack->getTaskNames());
    $this->assertContains('first_stack_2', $stack->getTaskNames());
    // Verify that 2 came before one.
    $onePosition = array_search('first_stack_1', $stack->getTaskNames());
    $twoPosition = array_search('first_stack_2', $stack->getTaskNames());
    $this->assertGreaterThan($twoPosition, $onePosition);
  }

  /**
   * Set the tasks array before scanning for new tasks.
   */
  public function testSetTasks() {
    $tasks = array(
      'task_one' => new Task('task_one')
    );
    $loader = new TaskLoader();
    $loader->setTasks($tasks);
    $this->assertEquals(1, count($loader->getTasks()));
    $tasks['task_two'] = new Task('task_two');
    $message = 'The internal task array properly maintains a reference to the task array.';
    $this->assertEquals(2, count($loader->getTasks()), $message);
    $loader->scanClass('\Fetcher\Tests\Fixtures\Tasks\TaskStackAnnotation');
    // Ensure references are properly maintained.
    $this->assertEquals(5, count($loader->getTasks()));
    $this->assertEquals(5, count($tasks));
  }

}


