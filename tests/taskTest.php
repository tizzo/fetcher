<?php
require_once "vendor/autoload.php";

// Load domain classes
use \Fetcher\Task\Task,
  \Fetcher\Site,
  \Fetcher\Task\TaskRunException;


class TaskTest extends PHPUnit_Framework_TestCase {

  /**
   * Test message preparation.
   */
  public function testPrepMessage() {
    $task = new Task('foo');
    $this->assertEquals($task->fetcherTask, 'foo');
    $options = array(
      'foo' => 'bar',
      'baz' => 'bot'
    );
    $message = 'This is a message about [[foo]] with info about [[baz]].';
    $prepared = $task->prepMessage($message, $options);
    $this->assertEquals($prepared, 'This is a message about bar with info about bot.');
  }

  /**
   * Test task runs with 
   */
  public function testRun() {
    $site = new Site();
    $history = array();
    $site['log'] = $site->protect(function ($message) use (&$history) {
      $history[] = $message;
    });
    $task = new Task();
    $ran = FALSE;
    $test = $this;
    $task->callable = function($site) use (&$ran, &$test) {
      $ran = TRUE; 
      $test->assertInstanceOf('\Fetcher\Site', $site);
    };
    $task->beforeMessage = 'before message';
    $this->assertEquals($ran, FALSE);
    $task->run($site);
    $this->assertEquals($ran, TRUE);
    $this->assertContains('before message', $history);
    $history = array();
    $task->afterMessage = 'after message';
    $task->run($site);
    $this->assertContains('after message', $history);
  }

}
 
