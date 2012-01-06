<?php

namespace Ignition\DB;
use Symfony\Component\Process\Process;

class Mysql {

  private $username = FALSE;

  private $password = FALSE;

  private $database = FALSE;

  private $db_spec = array();

  private $container = FALSE;

  public function __construct(\Pimple $container) {
    /*
    $username = $container['db.username'];
    $password = $container['db.password'];
    $database = $container['db.database'];
    */
    $this->db_spec  = $container['dbSpec'];
    $this->container = $container;
  }

  /**
   * Returns the drush driver for this database.
   */
  static public function getDriver() {
    return 'mysql';
  }

  public function exists() {
    return $this->executeQuery("SELECT 1;")->isSuccessful();
  }

  public function createDatabase() {
    // TODO: escape database name.
    $result = $this->executeQuery('create database ' . $database)
      ->isSuccessful();
    if (!$result) {
      throw new \Ignition\Exception\IgnitionException(sprintf('The database %s could not be created.', $database));
    }
  }

  public function checkUserExists($user) {
    $sql = "SELECT user FROM mysql.user WHERE user='%s'";
    //shell_exec(sprintf("mysql -e 'create database %s'", $database));
  }
  public function createUser($database, $password) {
    //sql = "mysql -e 'create user \"%s\"@\"localhost\" identified by \"%s\"'" % (siteName, password)
  }
  
  public function grantAccessToUser($database) {
    //sql = "mysql -e 'grant all on %s.* to \"%s\"@\"localhost\"'" % (siteName, siteName)
    //sql = "mysql -e 'flush privileges'"
  }

  /**
   * Execute a MySQL query at the command line.
   */
  protected function executeQuery($command) {
    // TODO: Allow the mysql path to be specified?
    $base_command = 'mysql';

    $config = $this->container;

    $base_command .= ' --database=' . escapeshellarg($config['database.database']);

    if ($config['database.admin.user']) {
      $base_command .= ' --user=' . escapeshellarg($config['database.admin.user']);
    }
    if ($config['database.admin.password']) {
      $base_command .= ' --password=' . escapeshellarg($config['database.admin.password']);
    }
    if ($config['database.admin.hostname']) {
      $base_command .= ' --host=' . escapeshellarg($config['database.admin.hostname']);
    }
    if ($config['database.admin.port']) {
      $base_command .= ' --port=' . escapeshellarg($config['database.admin.port']);
    }

    $command = $base_command . ' -e ' . escapeshellarg($command);
    drush_log(dt('Executing MySQL command `@command`', array('@command' => $command)));
    $process = new Process($command);

    if ($config['simulate']) {
      return $process;
    }

    $process->run();
    return $process;
  }
}

