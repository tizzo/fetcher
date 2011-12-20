<?php

namespace Ignition\System;

// 
class Debian extends Posix {

  public function __construct() {
  }

  /** 
   * 
   */
  public function getWebRoot() {
    return '/var/www';
  }

  // TODO: This really should be different for different servers.
  public function getWebUser() {
    return 'www-data';
  }

  public function buildApacheRestart() {
    return '/etc/init.d/apache2 restart';
  }
}
