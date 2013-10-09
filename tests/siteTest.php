<?php
require_once "vendor/autoload.php";

use \Fetcher\Site;
 
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
}
