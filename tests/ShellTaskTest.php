<?php
require_once "vendor/autoload.php";
/**
 * @File
 *    Test shell task execution.
 */

use \Fetcher\Task\ShellTask,
  \Fetcher\Site,
  \Fetcher\Task\TaskRunException;

class ShellTaskTest extends PHPUnit_Framework_TestCase {

  /**
   * Get a site object.
   */
  public function getSite(&$history) {
    $site = new Site();
    $site['log'] = $site->protect(function ($message) use (&$history) {
      $history[] = $message;
    });
    return $site;
  }

  /**
   * Test running a test in child directory.
   */
  public function testRunningTestInChildDirectory() {
    $task = new ShellTask('ls', 'tests');
    $history = array();
    $site = $this->getSite($history);
    $task->run($site);
    $this->assertContains('ShellTaskTest.php', $task->getOutput());
    $this->assertContains('Running shell command `ls`...', $history);
    $this->assertContains('    > ShellTaskTest.php', $history);
  }

  /**
   * Ensure getting output without running throws an excetion.
   *
   * @expectedException Exception
   */
  public function testGetOutputWithoutRun() {
    $task = new ShellTask('foo', 'bar');
    $task->getOutput();
  }

  /**
   * Verify execution of a command fails.
   *
   * @expectedException \Fetcher\Task\TaskException
   */
  public function testCommandFailureThowsException() {
    $task = new ShellTask('fetcher-not-a-real-command');
    $history = array();
    $site = $this->getSite($history);
    $task->run($site);
  }

}
