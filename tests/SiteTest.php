<?php
require_once "vendor/autoload.php";

use \Fetcher\Site,
    \Fetcher\Exception\FetcherException,
    \Fetcher\Task\TaskStack,
    \Fetcher\Task\Task;
 
/**
 * Tests the \Fetcher\Site() class.
 */
class SiteTest extends PHPUnit_Framework_TestCase {

  /**
   * Test the conifgure() method.
   */
  public function testConfigure() {
    $conf = array('foo' => 'bar');
    $site = new Site($conf);
    $this->assertEquals($site['foo'], 'bar');
    $conf = array(
      'foo' => 'bot',
      'beep' => 'boop',
    );
    $site->configure($conf);
    $this->assertEquals($site['foo'], 'bot');
    $this->assertEquals($site['beep'], 'boop');
    $conf = array(
      'foo' => 'baz',
      'monkey' => 'banana',
    );
    $site->configure($conf, $overrideExisting = FALSE);
    $this->assertEquals($site['foo'], 'bot');
    $this->assertEquals($site['beep'], 'boop');
    $this->assertEquals($site['monkey'], 'banana');
  }

  /**
   * Test the setDefaultConfigration() method.
   */
  public function testSetDefaultConfiguration() {
    $site = new Site();
    $this->assertFalse(isset($site['foo']));
    $site->setDefaultConfigration('foo', 'bar');
    // Ensure we can set an unset key.
    $this->assertEquals($site['foo'], 'bar');
    $site->setDefaultConfigration('foo', 'baz');
    // Ensure we will not override a set key.
    $this->assertEquals($site['foo'], 'bar');
  }

  /**
   * Test the configureFromEnvironment() method.
   */
  public function testConfigureFromEnvironment() {
    $site = new Site();
    $site['foo'] = NULL;
    $site['environments'] = array(
      'dev' => array(
        'foo' => 'bar',
      ),
      'stage' => array(
        'foo' => 'baz',
      ),
    );
    $this->assertEquals(NULL, $site['foo'], 'Original value set successfully.');
    $site->configureFromEnvironment('dev');
    $this->assertEquals('bar', $site['foo'], 'Original value set successfully.');
    $this->assertContains('foo', $site['configuration.ephemeral']);
    $site->configureFromEnvironment('stage');
    $this->assertEquals('baz', $site['foo'], 'Original value set successfully.');
    try {
      $site->configureFromEnvironment('nonsense');
      $this->assertTrue('FALSE', 'An exception should have been thrown.');
    }
    catch(FetcherException $e) {
      $this->assertTrue(TRUE, 'Exception successfully thrown and caught.');
    }
  }

  /**
   * Test the addSubTask() method.
   */
  public function testAddSubTask() {
    $site = new Site();
    $stack = new TaskStack('foo');
    $task1 = new Task('bar');
    $site->addTask($stack);
    // Add a task object.
    $site->addSubTask('foo', $task1);
    $this->assertContains($task1, $site->getTask('foo')->tasks, 'Subtask successfully added to task stack.');
    // Add a task by task name.
    $task2 = new Task('baz');
    $site->addTask($task2);
    $site->addSubTask('foo', 'baz');
    $this->assertContains($task2, $site->getTask('foo')->tasks, 'Subtask successfully added to task stack.');
    try {
      $site->addSubtask('nonsense', $task1);
      $this->assertTrue('FALSE', 'An exception should have been thrown.');
    }
    catch(FetcherException $e) {
      $this->assertTrue(TRUE, 'Exception successfully thrown and caught.');
    }
  }

  /**
   * Test the getEnvironment() method.
   */
  public function testGetEnvironment() {
    $site = new Site();
    $site['environments'] = array(
      'dev' => array(
        'root' => '/foo',
      ),
      'staging' => array(
        'root' => '/bar',
      ),
    );
    $dev = $site->getEnvironment('dev');
    $staging = $site->getEnvironment('staging');
    $this->assertEquals('/foo', $dev['root']);
    $this->assertEquals('/bar', $staging['root']);
  }

}
