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
      'number' => 10,
    );
    $site->configure($conf);
    $this->assertEquals($site['foo'], 'bot');
    $this->assertEquals($site['beep'], 'boop');
    $this->assertEquals($site['number'], 10);
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
  }

  /**
   * Test that configure environment throws an exception with a bad environment.
   *
   * @expectedException Fetcher\Exception\FetcherException
   */
  public function testConfigureFromEnvironmentWithBadEnvironmentThrowsExcpetion() {
    $site = new Site();
    $site->configureFromEnvironment('nonsense');
  }

  /**
   * Test the addSubTask() method.
   */
  public function testAddSubTask() {
    $site = new Site();
    $stack = new TaskStack('foo');
    $site->addTask($stack);
    // Add a task object.
    $task1 = new Task('bar');
    $site->addSubTask('foo', $task1);
    $this->assertContains($task1, $site->getTask('foo')->tasks, 'Subtask successfully added to task stack.');
    // Add a task by task name.
    $task2 = new Task('baz');
    $site->addTask($task2);
    $site->addSubTask('foo', 'baz');
    $this->assertContains($task2, $site->getTask('foo')->tasks, 'Subtask successfully added to task stack.');
    $this->assertEquals('array', gettype($site->getTasks()));
  }

  /**
   * Test that addSubTask() throws an exception with a bad subtask specified.
   *
   * @expectedException Fetcher\Exception\FetcherException
   */
  public function testAddSubTaskThrowsAnExceptionWithABadSubtask() {
    $site = new Site();
    $site->addSubtask('nonsense', new Task('task1'));
  }


  /**
   * Test that runTask() throws an exception with a bad task specified.
   *
   * @expectedException Fetcher\Exception\FetcherException
   */
  public function testRunTaskThrowsAnExceptionWithABadSubtask() {
    $site = new Site();
    $site->runTask('nonsense');
  }

  /**
   * Test the runTask() method.
   */
  public function testRunTask() {
    $site = new Site();
    $task = Phake::Mock('Fetcher\Task\Task');
    $task->fetcherTask = 'task1';
    $site->addTask($task);
    $site->runTask('task1');
    Phake::verify($task)->run($site);
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

  /**
   * Test that the default tasks are defined in the appropriate order.
   */
  public function testDefaultSiteTasks() {
    $site = new Site();
    $tasks = $site->getTask('ensure_site')->getTaskNames();
    $this->assertEquals('ensure_working_directory', $tasks[0]);
    $this->assertEquals('ensure_code', $tasks[1]);
    $this->assertEquals('ensure_settings_file', $tasks[2]);
    $this->assertEquals('ensure_sym_links', $tasks[3]);
    $this->assertEquals('ensure_drush_alias', $tasks[4]);
    $this->assertEquals('ensure_database_connection', $tasks[5]);
    $this->assertEquals('ensure_site_info_file', $tasks[6]);
    $this->assertEquals('ensure_server_host_enabled', $tasks[7]);
    // Remove site tasks can be performed in any order.
    $tasks = $site->getTask('remove_site')->getTaskNames();
    $this->assertContains('remove_working_directory', $tasks);
    $this->assertContains('remove_drush_aliases', $tasks);
    $this->assertContains('remove_database', $tasks);
    $this->assertContains('remove_vhost', $tasks);
  }

  /**
   * Test the ensureDatabase() method.
   */
  public function testEnsureDatabase() {
    $site = $this->getMockSite();
    $database = $site['database'];
    Phake::when($database)
      ->canConnect()
      ->thenReturn(FALSE);
    $site->ensureDatabase();
    Phake::verify($database)->canConnect();
    Phake::verify($database)->exists();
    Phake::verify($database)->createDatabase();
    Phake::verify($database)->userExists();
    Phake::verify($database)->createUser();
    Phake::verify($database)->grantAccessToUser();
    $site = $this->getMockSite();
    $database = $site['database'];
    Phake::when($database)
      ->canConnect()
      ->thenReturn(FALSE);
    Phake::when($database)
      ->userExists()
      ->thenReturn(TRUE);
    $site->ensureDatabase();
    Phake::verify($database)->grantAccessToUser();
  }

  /**
   * Test that getEnvironment() method throws an exception on invalid environment.
   *
   * @expectedException Fetcher\Exception\FetcherException
   */
  public function testGetEnvironmentThrows() {
    $site = $this->getMockSite();
    $site->getEnvironment('nonsense');
  }

  /**
   * Test that configureFromEnvironment() method throws an exception on invalid environment.
   *
   * @expectedException Fetcher\Exception\FetcherException
   */
  public function testConfigureFromEnvironmentThrows() {
    $site = $this->getMockSite();
    $site['environment.remote'] = 'remote';
    $site->configureFromEnvironment();
  }


  /**
   * Test the ensureSiteFolder() method.
   */ 
  public function testEnsureSiteFolder() {
    $site = $this->getMockSite();
    $site->ensureSiteFolder();
    $system = $site['system'];
    Phake::verify($site['system'])->ensureFolderExists('/var/www/test/code/sites/default', null, 'apache');
  }

  /**
   * Test the ensureWorkingDirectory() method.
   */
  public function testEnsureWorkingDirectory() {
    $site = $this->getMockSite();
    $system = $site['system'];
    $site->ensureWorkingDirectory();
    Phake::verify($system)->ensureFolderExists('/var/www/test');
    Phake::verify($system)->ensureFolderExists('/var/www/test/logs');
    Phake::verify($system)->ensureFolderExists('/var/www/test/public_files', NULL, 'apache', 0775);
    Phake::verify($system)->ensureFolderExists('/var/www/test/private_files', NULL, 'apache', 0775);
    Phake::verify($system)->ensureFileExists('/var/www/test/logs/access.log');
    Phake::verify($system)->ensureFileExists('/var/www/test/logs/mail.log');
    Phake::verify($system)->ensureFileExists('/var/www/test/logs/watchdog.log');
  }

  /**
   * Test the ensureSettingsFileExists() method.
   */
  public function testEnsureSettingsFileExists() {
    $site = $this->getMockSite();
    $site['database.user.password'] = 'foo';
    Phake::when($site['system'])
      ->isFile($site['site.directory'] . '/site-settings.php')
      ->thenReturn(TRUE);
    $site->ensureSettingsFileExists();
    $siteFolder = '/var/www/test/code/sites/default';
    Phake::verify($site['system'])->ensureFolderExists($siteFolder);
    Phake::verify($site['system'])->writeFile($siteFolder . '/settings.php', $this->getSampleData('example_settings.php'));
  }

  /**
   * Test the ensure ensureCode() method.
   */
  public function testEnsureCode() {
    $site = $this->getMockSite();
    Phake::when($site['system'])
      ->isDir($site['site.code_directory'] . '/' . $site['webroot_subdirectory'])
      ->thenReturn(TRUE);
    $site->ensureCode();
    Phake::verify($site['code_fetcher'])->setup();
    $message = 'Properly detect a named subdirectory that holds index.php';
    $this->assertEquals('/var/www/test/code/docroot', $site['site.code_directory'], $message);
    $site = $this->getMockSite();
    Phake::when($site['system'])
      ->isDir($site['site.code_directory'])
      ->thenReturn(TRUE);
    Phake::when($site['system'])
      ->isDir($site['site.code_directory'] . '/' . $site['webroot_subdirectory'])
      ->thenReturn(FALSE);
    $site->ensureCode();
    Phake::verify($site['code_fetcher'])->update();
    $message = 'Detect a properly named subdirectory that holds index.php.';
    $this->assertEquals('/var/www/test/code', $site['site.code_directory'], $message);
  }

  /**
   * Test fetchInfo() method.
   */
  public function testFetchInfo() {
    $site = new Site();
    $this->assertEquals(FALSE, $site->fetchInfo());

    $site = $this->getMockSite();
    Phake::when($site['system'])
      ->isFile($site['site.info path'])
      ->thenReturn(TRUE);
    $example = file_get_contents(__DIR__ . '/fixtures/example.yaml');
    Phake::when($site['system'])
      ->fileGetContents($site['site.info path'])
      ->thenReturn($example);
    $site->fetchInfo();
    $this->assertEquals('bar', $site['foo']);
    $this->assertEquals('bot', $site['baz']);

    $site = $this->getMockSite();
    Phake::when($site['system'])
      ->isFile($site['site.code_directory'])
      ->thenReturn(TRUE);
    Phake::when($site['info_fetcher'])
      ->getInfo('test')
      ->thenReturn(array('foo' => 'bar'));
    $site->fetchInfo();
    $this->assertEquals('bar', $site['foo']);
  }

  /**
   * Test setDefaults() method.
   */
  public function testSetDefaults() {
    $site = $this->getMockSite();
    $site->setDefaults();
    ob_start();
    $site['log']('test output');
    $this->assertEquals('test output' . PHP_EOL, ob_get_contents());
    ob_end_clean();
    ob_start();
    $site['user prompt']('Does this work?');
    $this->assertEquals('Does this work?' . PHP_EOL, ob_get_contents());
    ob_end_clean();
    ob_start();
    $site['user confirm']('Does this work?');
    $this->assertEquals('Does this work? (Y/N) Y' . PHP_EOL, ob_get_contents());
    ob_end_clean();
    ob_start();
    $site['print']('output');
    $this->assertEquals('output', ob_get_contents());
    ob_end_clean();
    $site['code_fetcher.vcs_mapping'] = array(
      'foo' => 'bar',
    );
    $site['vcs'] = 'foo';
    $this->assertEquals('bar', $site['code_fetcher.class']);
    $site['profile'] = 'bear';
    $this->assertEquals('bear', $site['profile.package']);
    $this->assertEquals(20, strlen($site['random']()));
    $classes = array(
      'server' => 'server class',
      'system' => 'system class',
      'database' => 'database class',
      'code_fetcher' => 'code_fetcher.class',
      'info_fetcher' => 'info_fetcher.class',
      'database_synchronizer' => 'database_synchronizer.class',
      'file synchronizer' => 'file synchronizer class',
    );
    foreach ($classes as $service => $class) {
      $site[$class] = 'stdClass';
      $this->assertInstanceOf('stdClass', $site[$service]);
    }
    $site = $this->getMockSite();
    $this->assertEquals('local', $site['system hostname']);
  }

  /**
   * Test configureWithSiteInfo() method.
   */
  public function testConfigureWithSiteInfo() {
    $site = $this->getMockSite();
    $this->assertEquals(FALSE, $site->configureWithSiteInfoFile());
  }

  /**
   * Test ensureSiteInfoFileExists() method.
   */
  public function testEnsureSiteInfoFileExists() {
    $site = $this->getMockSite();
    $site->ensureSiteInfoFileExists();
    $expected = Yaml::dump($site->exportConfiguration(), 5, 2);
    Phake::verify($site['system'])->writeFile($site['site.info path'], $expected);
  }

  /**
   * Test ensureSymLinks() method.
   */
  public function testEnsureSymLinks() {
    $site = $this->getMockSite();
    $site['symlinks'] = array(
      '/some/first/path' => '/some/second/path',
    );
    $site->ensureSymLinks();
    Phake::verify($site['system'])->ensureSymLink('/some/first/path', '/some/second/path');
  } 

  /**
   * Test ensureDrushAlias() method.
   */
  public function testEnsureDrushAlias() {
    $site = $this->getMockSite();
    // Ensure $site will initialize environments.
    unset($site['environments']);
    $site->ensureDrushAlias();
    Phake::verify($site['system'])->writeFile('/.drush/test.aliases.drushrc.php', $this->getSampleData('simple_alias.php'));
    $site = $this->getMockSite();
    $item = new stdClass();
    $item->objectValue = 'bar';
    $item->objectArray = array(
      'ding' => 'dong',
    );
    $site['environments'] = array(
      'foo' => array(
        'array_value' => 'foo',
        'fetcher' => array(
          'object_child' => $item,
          'array_child' => array(
            'way_down' => 'here',
          ),
        ),
      )
    );
    $site->ensureDrushAlias();
    Phake::verify($site['system'])->writeFile('/.drush/test.aliases.drushrc.php', $this->getSampleData('complex_alias.php'));
  }


  /**
   * Test the ensureSiteEnabled() method.
   */
  public function testEnsureSiteEnabled() {
    $site = $this->getMockSite();
    $site->ensureSiteEnabled();
    $server = $site['server'];
    Phake::when($server)
      ->siteIsEnabled()
      ->thenReturn(TRUE);
    Phake::verify($server)->siteIsEnabled();
    $site = $this->getMockSite();
    $server = $site['server'];
    $site->ensureSiteEnabled();
    Phake::verify($server)->siteIsEnabled();
    Phake::verify($server)->ensureSiteConfigured();
    Phake::verify($server)->ensureSiteEnabled();
    Phake::verify($server)->restart();
  }

  /**
   * Test the syncDatabase() method.
   */
  public function testSyncDatabase() {
    $site = $this->getMockSite();
    $site->syncDatabase();
    Phake::verify($site['database_synchronizer'])->syncDB();
  }

  /**
   * Test the syncFiles() method.
   */
  public function testSyncFiles() {
    $site = $this->getMockSite();
    $site->syncFiles('private');
    Phake::verify($site['file synchronizer'])->syncFiles('private');
  }

  /**
   * Test the removeWorkingDirectory() method.
   */
  public function testRemoveWorkingDirectory() {
    $site = $this->getMockSite();
    $site->removeWorkingDirectory();
    Phake::verify($site['system'])->ensureDeleted('/var/www/test');
    $site1 = $this->getMockSite();
    $site2 = $this->getMockSite();
    $site2->removeWorkingDirectory($site1);
    Phake::verify($site1['system'])->ensureDeleted('/var/www/test');
  }

  /**
   * Test the removeDrushAliases() method.
   */
  public function testRemoveDrushAliases() {
    $site = $this->getMockSite();
    $site->removeDrushAliases();
    Phake::verify($site['system'])->ensureDeleted('/.drush/test.aliases.drushrc.php');
    $site1 = $this->getMockSite();
    $site2 = $this->getMockSite();
    $site2->removeDrushAliases($site1);
    Phake::verify($site1['system'])->ensureDeleted('/.drush/test.aliases.drushrc.php');
  }

  /**
   * Test removeDatabase() method.
   */
  public function testRemoveDatabase() {
    $site = $this->getMockSite();
    Phake::when($site['database'])
      ->exists()
      ->thenReturn(TRUE);
    Phake::when($site['database'])
      ->userExists()
      ->thenReturn(TRUE);
    $site->removeDatabase();
    Phake::verify($site['database'])->exists();
    Phake::verify($site['database'])->removeDatabase();
    Phake::verify($site['database'])->userExists();
    Phake::verify($site['database'])->removeUser();
  }

  /**
   * Test removeVirtualhost() method.
   */
  public function testRemoveVirtualhost() {
    $site = $this->getMockSite();
    $site->removeVirtualhost();
    Phake::verify($site['server'])->ensureSiteRemoved();
  }

  public function getSampleData($name) {
    return trim(file_get_contents(__DIR__ . '/fixtures/' . $name));
  }

  /**
   * Get a site object with fully mocked dependencies.
   */
  public function getMockSite() {
    $site = new Site();
    $site['name'] = 'test';
    $site['name.global'] = 'test';
    $site['hostname'] = 'test.local';
    $site['system hostname'] = 'local';
    $site['server.webroot'] = '/var/www';
    $site['server.user'] = 'apache';
    $site['database.admin.user.name'] = NULL;
    $site['database.admin.user.password'] = NULL;
    $site['database.admin.port'] = NULL;
    $site['database.database'] = function($c) { return $c['name']; };
    $site['database.driver'] = 'mysql';
    $site['database.hostname'] = 'localhost';
    $site['database.user.password'] = 'foo';
    $site['database.user.name'] = 'test';
    $site['database.port'] = '';
    $site['database.prefix'] = '';
    $site['mysql.binary'] = 'mysql';
    $site['system'] = Phake::mock('Fetcher\System\Posix');
    $site['server'] = Phake::mock('Fetcher\Server\ServerInterface');
    $site['code_fetcher'] = Phake::mock('Fetcher\CodeFetcher\VCS\Git');
    $site['info_fetcher'] = Phake::Mock('Fetcher\InfoFetcher\InfoFetcherInterface');
    $site['database'] = Phake::Mock('Fetcher\DB\Mysql');
    $site['database_synchronizer'] = Phake::Mock('Fetcher\DBSynchronizer\DBSynchronizerInterface');
    $site['file synchronizer'] = Phake::Mock('Fetcher\FileSynchronizer\FileSynchronizerInterface');
    Phake::when($site['system'])
      ->getHostname()
      ->thenReturn('local');
    Phake::when($site['system'])
      ->getHostname()
      ->thenReturn('local');
    Phake::when($site['system'])
      ->getHostname()
      ->thenReturn('local');
    return $site;
  }
}
