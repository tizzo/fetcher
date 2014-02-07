<?php
require_once "vendor/autoload.php";

// Load domain classes
use \Fetcher\Task\ShellTask,
  \Fetcher\Site,
  \Fetcher\Task\TaskRunException;

class ShellTaskTest extends PHPUnit_Framework_TestCase {

  public function getSite(&$history) {
    $site = new Site();
    $site['log'] = $site->protect(function ($message) use (&$history) {
      $history[] = $message;
    });
    return $site;
  }

  public function testRunningTestInChildDirectory() {
    $task = new ShellTask('ls', 'tests');
    $history = array();
    $site = $this->getSite($history);
    $task->run($site);
    $this->assertContains('ShellTaskTest.php', $task->getOutput());
    $this->assertContains('Running shell command `ls`...', $history);
    $this->assertContains('    > ShellTaskTest.php', $history);
  }

  public function testCommandFailureThowsException() {
    $task = new ShellTask('fetcher-not-a-real-command');
    try {
      $history = array();
      $site = $this->getSite($history);
      $task->run($site);
      throw new \Exception('fail');
    }
    catch (TaskRunException $e) {
      $this->assertNotEmpty($e);
    }
  }
}

