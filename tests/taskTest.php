<?php
require_once "vendor/autoload.php";

// Load domain classes
use \Fetcher\Task\TaskLoader,
  \Fetcher\Task\TaskLoaderException;

// Load test fixture classes.
use \Fetcher\Tests\Fixtures\Tasks\TaskAnnotation,
  \Fetcher\Tests\Fixtures\Tasks\TaskAnnotationError;


class TaskTest extends PHPUnit_Framework_TestCase {

  /**
   * 
   */
  public function testPrepMessage() {
  }

}
 
