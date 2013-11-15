<?php
require_once "vendor/autoload.php";

use \Fetcher\Site,
    \Fetcher\Exception\FetcherException,
    \Fetcher\Task\TaskStack,
    \Fetcher\Task\Task;
 
class SiteTest extends PHPUnit_Framework_TestCase {

  public function testConfigureMethod() {
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
}
