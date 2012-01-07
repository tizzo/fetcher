<?php

namespace Ignition\Server;

class Apache2 {

  /**
   * Get the web user for this server.
   */
  public function webUser() {
    // TODO: Allow some kind of loading from the system.
    return 'www-data';
  }

}

