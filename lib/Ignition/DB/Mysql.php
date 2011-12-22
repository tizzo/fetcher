<?php

namespace Ignition\DB;
use Ignition\Base as Base;

class Mysql extends Base {

  private $username = FALSE;

  private $password = FALSE;

  private $database = FALSE;

  /**
   *
   */
  public function configure($configure) {
    foreach ($config as $name => $value) {
      if (isset($this->{$name})) {
        $this->{$name} = $value;
      }
    }
  }

  public function exists() {
    $sql = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = "%s"';
  }
  
  public function createDatabase($database) {
    // TODO: escape database name.
    shell_exec(sprintf("mysql -e 'create database %s'", $database));
  }

  public function createUser($database, $password) {
    //sql = "mysql -e 'create user \"%s\"@\"localhost\" identified by \"%s\"'" % (siteName, password)
  }
  
  public function grantAccessToUser($database) {
    //sql = "mysql -e 'grant all on %s.* to \"%s\"@\"localhost\"'" % (siteName, siteName)
    //sql = "mysql -e 'flush privileges'"
  }
}

