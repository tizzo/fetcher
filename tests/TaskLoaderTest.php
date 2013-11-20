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
    $this->assertContains('foo', $task->beforeTask);
    $this->assertContains('bar', $task->beforeTask);
    $this->assertContains('baz', $task->afterTask);
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

}


