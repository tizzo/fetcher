<?php
require_once "vendor/autoload.php";

// Load domain classes
use \Fetcher\Task\TaskLoader,
  \Fetcher\Task\TaskLoaderException;

// Load test fixture classes.
use \Fetcher\Tests\Fixtures\Tasks\TaskAnnotation,
  \Fetcher\Tests\Fixtures\Tasks\TaskAnnotationError;


class TaskLoaderTest extends PHPUnit_Framework_TestCase {

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
    $site = new \Fetcher\Site();
    $history = array();
    $site['log'] = $site->protect(function ($message) use (&$history) {
      $history[] = $message;
    });
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


}


