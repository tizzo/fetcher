<?php
require_once "vendor/autoload.php";

use \Fetcher\Utility\SettingsPHPGenerator;
 
/**
 * Tests the \Fetcher\Site() class.
 */
class SettingsPHPGeneratorTest extends PHPUnit_Framework_TestCase {

  /**
   * Test the compile() method.
   *
   * @expectedException \Exception
   */
  public function testSetException() {
    $compiler = new SettingsPHPGenerator();
    $compiler->set('foo', 'bar');
  }

  /**
   * Test setting an array of values.
   */
  public function testSetArray() {
    $compiler = new SettingsPHPGenerator();
    $value = array(
      'foo' => 'bar',
      'baz' => 'bot',
    );
    $compiler->set('variables', $value);
    $this->assertEquals($compiler->get('variables'), $value);
  }

  /**
   * Test setting an individual value within the array.
   */
  public function testSetArrayKey() {
    $compiler = new SettingsPHPGenerator();
    $compiler->set('iniSettings', 'bar', 'foo');
    $compiler->set('iniSettings', 'bot', 'baz');
    $value = array(
      'foo' => 'bar',
      'baz' => 'bot',
    );
    $this->assertEquals($compiler->get('iniSettings'), $value);
    $this->assertEquals($compiler->get('iniSettings', 'foo'), 'bar');
  }

  /**
   * Test compile without tag.
   */
  public function testCompileWithoutTag() {
    $compiler = new SettingsPHPGenerator();
    $output = $compiler->compile(FALSE);
    $this->assertEquals($output, '');
  }

  /**
   * Test compile with tag.
   */
  public function testCompileWithTag() {
    $compiler = new SettingsPHPGenerator();
    $output = $compiler->compile();
    $this->assertEquals($output, '<?php');
  }

  /**
   * Test compile with all options.
   */
  public function testCompile() {
    $compiler = new SettingsPHPGenerator();
    $compiler->set('iniSettings', -1, 'memory_limit');
    $compiler->set('variables', 1, 'offline_mode');
    $array_value = array(
      'default' => array(
        'multi' => 'level',
      ),
    );
    $compiler->set('variables', $array_value, 'some_array');
    $compiler->set('requires', 'sites/default/site-settings.php', 0);
    $compiler->compile();
    $this->assertEquals(file_get_contents(__DIR__ . '/fixtures/test_compile.php'), $compiler->compile());
  }

}
 
