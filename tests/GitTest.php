<?php
require_once "vendor/autoload.php";

use \Fetcher\Site,
    \Fetcher\Exception\FetcherException,
    \Fetcher\Task\TaskStack,
    \Fetcher\Task\Task,
    \Symfony\Component\Yaml\Yaml,
    \Phake;
 
/**
 * Tests the \Fetcher\Site() class.
 */
class GitTest extends PHPUnit_Framework_TestCase {

  /**
   * Test the setup() method.
   */
  public function testSetup() {
    $site = $this->getMockSite();
  }

  /**
   * Get a site object with fully mocked dependencies.
   */
  public function getMockSite($history = FALSE) {
    if (empty($history)) {
      $history = array();
    }
    $site = new Site();
    $site['name.global'] = 'Test';
    $site['server.webroot'] = '/var/www';
    $site['log'] = function($message) use ($history) {
      $history[] = $message;
    };
    return $site;
  }
}
