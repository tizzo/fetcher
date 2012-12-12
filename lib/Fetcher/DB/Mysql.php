<?php

namespace Fetcher\DB;

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
    $site->setDefaultConfigration('database.admin.hostname', 'localhost');
    $site->setDefaultConfigration('database.admin.port', NULL);
    $site->setDefaultConfigration('database.database', function($c) { return $c['name']; });
    $site->setDefaultConfigration('database.hostname', 'localhost');
    $site->setDefaultConfigration('database.user.password', $passwordGenerator);
    $site->setDefaultConfigration('database.user.name', function($c) { return $c['name']; });
    $site->setDefaultConfigration('database.user.hostname', function($c) { return $c['hostname']; });
    $site->setDefaultConfigration('database.port', 3306);
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
  }

  /**
   * Remove the database.
   */
  public function removeDatabase() {
    $database = $this->site['database.database'];
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
   * Execute a MySQL query at the command line.
   */
  protected function executeQuery($command, $setDatabase = TRUE) {
    // TODO: Allow the mysql path to be specified?
    $base_command = 'mysql';

    $site = $this->site;

    if ($setDatabase) {
      $base_command .= ' --database=' . escapeshellarg($site['database.database']);
    }

    if (!is_null($site['database.admin.user.name'])) {
      $base_command .= ' --user=' . escapeshellarg($site['database.admin.user.name']);
    }
    if (!is_null($site['database.admin.user.password'])) {
      $base_command .= ' --password=' . escapeshellarg($site['database.admin.user.password']);
    }
    if (!is_null($site['database.admin.hostname'])) {
      $base_command .= ' --host=' . escapeshellarg($site['database.admin.hostname']);
    }
    if (!is_null($site['database.admin.port'])) {
      $base_command .= ' --port=' . escapeshellarg($site['database.admin.port']);
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

