<?php

namespace Fetcher\DB;

/**
 * The class manages our MySQL databases and users.
 */
class Mysql {

  private $username = FALSE;

  private $password = FALSE;

  private $database = FALSE;

  private $db_spec = array();

  private $site = FALSE;

  public function __construct(\Fetcher\SiteInterface $site) {
    $this->site = $site;

    $passwordGenerator = $site->share(
      function($c) {
        return $c['random']();
      }
    );

    // Setup the administrative db credentials ().
    $site->setDefaultConfigration('database.admin.user.name', NULL);
    $site->setDefaultConfigration('database.admin.user.password', NULL);
    $site->setDefaultConfigration('database.admin.port', NULL);
    $site->setDefaultConfigration('database.database', function($c) { return $c['name']; });
    $site->setDefaultConfigration('database.hostname', 'localhost');
    $site->setDefaultConfigration('database.user.password', $passwordGenerator);
    $site->setDefaultConfigration('database.user.name', function($c) { return $c['name']; });
    if ($site['database.hostname'] == 'localhost') {
      $site->setDefaultConfigration('database.user.hostname', 'localhost');
    }
    else {
      $site->setDefaultConfigration('database.user.hostname', function($c) { return $c['system hostname']; });
    }
    $site->setDefaultConfigration('database.port', 3306);
    $site->setDefaultConfigration('mysql.binary', 'mysql');
    $site->addEphemeralKey('data.admin.user.password');
  }

  /**
   * Returns the drush driver for this database.
   */
  static public function getDriver() {
    return 'mysql';
  }

  /**
   * Check that the database exists.
   */
  public function exists() {
    return $this->executeQuery("SELECT 1;")->isSuccessful();
  }

  /**
   * Test to see whether the user can connect to the database.
   */
  public function canConnect() {
    $config = $this->getQueryConfig(FALSE);
    return $this->executeQuery("SELECT 1;", TRUE, $config)->isSuccessful();
  }

  /**
   * Create the database.
   */
  public function createDatabase() {
    $database = $this->site['database.database'];
    $result = $this->executeQuery('create database ' . $database, FALSE)->isSuccessful();
    if (!$result) {
      throw new \Fetcher\Exception\FetcherException(sprintf('The database %s could not be created.', $database));
    }
  }

  /**
   * Check that the user exists.
   */
  public function userExists() {
    $conf = $this->site;
    $process = $this->executeQuery(sprintf("SELECT user FROM mysql.user WHERE User='%s' AND Host='%s'", $conf['database.user.name'], $conf['database.user.hostname']), FALSE);
    if (!$process->isSuccessful()) {
      throw new \Fetcher\Exception\FetcherException('MySQL command failed.');
    }
    if ($process->getOutput() == '') {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * Create a database user.
   */
  public function createUser() {
    $conf = $this->site;
    $command = sprintf('create user "%s"@"%s" identified by "%s"', $conf['database.user.name'], $conf['database.user.hostname'], $conf['database.user.password']);
    $this->executeQuery($command, FALSE);
  }

  /**
   * Grant access for the database to the site database user.
   */
  public function grantAccessToUser() {
    $conf = $this->site;
    $command = sprintf('grant all on %s.* to "%s"@"%s"', $conf['database.database'], $conf['database.user.name'], $conf['database.user.hostname']);
    $this->executeQuery($command, FALSE);
    $this->executeQuery('flush privileges', FALSE);
    if (!$this->canConnect()) {
      throw new \Fetcher\Exception\FetcherException('The existing MySQL user could not access the existing MySQL database after GRANT query was run.');
    }
  }

  /**
   * Remove the database.
   */
  public function removeDatabase() {
    $database = $this->site['database.database'];
    $this->site['log'](dt('Deleting database %database', array('%database' => $database)));
    $result = $this->executeQuery('drop database ' . $database, FALSE)->isSuccessful();
    if (!$result) {
      throw new \Fetcher\Exception\FetcherException(sprintf('The database %s could not be dropped.', $database));
    }
  }

  /**
   * Remove the database user.
   */
  public function removeUser() {
    $conf = $this->site;
    $command = sprintf('drop user "%s"@"%s"', $conf['database.user.name'], $conf['database.user.hostname']);
    $this->executeQuery($command, FALSE);
  }

  /**
   * Extracts the configuration for a database query from a site object.
   *
   * @param $admin
   *   (bool) Indicates whether to build the admin rather than regular config.
   * @param $site
   *   (\Fetcher\Site) A Fetcher Site object to extract configuration from.
   */
  private function getQueryConfig($admin = FALSE, $site = NULL) {
    if (is_null($site)) {
      $site = $this->site;
    }
    if ($admin) {
      $admin = '.admin';
    }
    $config = array();
    $config['database.database'] = $site['database.database'];
    $keys = array(
      'user' => "database{$admin}.user.name",
      'password' => "database{$admin}.user.password",
      'port' => 'database.port',
      'hostname' => 'database.hostname',
    );
    foreach ($keys as $key => $value) {
      if (!is_null($site[$value])) {
        $config[$key] = $site[$value];
      }
    }
    return $config;
  }

  /**
   * Execute a MySQL query at the command line.
   */
  protected function executeQuery($command, $setDatabase = TRUE, $config = NULL) {

    if (is_null($config)) {
      $config = $this->getQueryConfig(TRUE);
    }

    $site = $this->site;
    $base_command = $site['mysql.binary'];

    if ($setDatabase) {
      $base_command .= ' --database=' . escapeshellarg($config['database.database']);
    }

    if (!empty($config['user'])) {
      $base_command .= ' --user=' . escapeshellarg($config['user']);
    }
    if (!empty($config['password'])) {
      $base_command .= ' --password=' . escapeshellarg($config['password']);
    }
    if (!empty($config['hostname'])) {
      $base_command .= ' --host=' . escapeshellarg($config['hostname']);
    }
    if (!empty($config['port'])) {
      $base_command .= ' --port=' . escapeshellarg($config['port']);
    }

    $command = $base_command . ' -e ' . escapeshellarg($command);
    $site['log'](dt('Executing MySQL command `@command`', array('@command' => $command)));
    $process = $this->site['process']($command);

    if ($site['simulate']) {
      return $process;
    }

    $process->run();
    return $process;
  }
}

