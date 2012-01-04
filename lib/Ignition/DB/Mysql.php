<?php

namespace Ignition\DB;

class Mysql {

  private $username = FALSE;

  private $password = FALSE;

  private $database = FALSE;

  private $db_spec = array();

  public function __construct(\Pimple $container) {
    /*
    $username = $container['db.username'];
    $password = $container['db.password'];
    $database = $container['db.database'];
    */
    $this->db_spec  = $container['dbSpec'];
  }

  /**
   * Returns the drush driver for this database.
   */
  static public function getDriver() {
    return 'mysql';
  }

  public function exists() {
    $db_spec = $this->getAdminDbSpec();
    $exists = drush_sql_db_exists($db_spec);
    $exists ? drush_log('exists') : drush_log('not exists');
    return ;
  }

  public function getAdminDbSpec() {
    $adminDBSpec = $this->db_spec;
    // If we have an option set for the sql user, use it.
    $adminDBSpec['username'] = drush_get_option('ignition-sql-user', 'root');
    // If we have an option set for the sql password, use it.
    if (drush_get_option('ignition-sql-password', FALSE)) {
      $adminDBSpec['password'] = drush_get_option('ignition-sql-password');
    }
    else {
      unset($adminDBSpec['password']);
    }
    return $adminDBSpec;
  }
  
  public function createDatabase() {
    // TODO: escape database name.
    //shell_exec(sprintf("'create database %s'", $database));
    $adminDBSpec = $this->getAdminDbSpec();
    return drush_sql_empty_db($adminDBSpec);
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
    $command = 'mysql -e "' . $command . ';"';

    if (drush_get_context('DRUSH_VERBOSE')) {
      $function = 'drush_shell_exec_interactive';
    }
    else {
      $function = 'drush_shell_exec';
    }
    return call_user_func_array($function, $args);
  }
}

