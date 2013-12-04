<?php

namespace Fetcher\Server;

interface ServerInterface {
  /**
   * Check whether this site appears to be configured and configure it if not.
   */
  public function ensureSiteConfigured();

  /**
   * Ensure that the configured site has been enabled.
   */
  public function ensureSiteEnabled();

  /**
   * Ensure that the configured site has been disabled.
   */
  public function ensureSiteDisabled();

  /**
   * Restart the server to load the configuration.
   *
   * Note this should be done cracefully if possible.
   */
  public function restart();

}

