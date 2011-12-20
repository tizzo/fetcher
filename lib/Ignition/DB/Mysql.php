<?php

namespace Ignition\DB;

// 
class Mysql /*extends Posix implements Ignition\System\SystemInterface*/ {

  private $username = FALSE;

  private $password = FALSE;

  private $database = FALSE;

  public function __construct($config = FALSE) {
  }
  
  public function createDatabase($database) {
    //$sql = "mysql -e 'create database %s'" % (siteName)
  }

  public function createUser($database, $password) {
    //sql = "mysql -e 'create user \"%s\"@\"localhost\" identified by \"%s\"'" % (siteName, password)
  }
  
  public function grantAccessToUser($database) {
    //sql = "mysql -e 'grant all on %s.* to \"%s\"@\"localhost\"'" % (siteName, siteName)
    //sql = "mysql -e 'flush privileges'"
  }
}

