<?php

namespace Fetcher\Server;

class Apache2 {

  protected $site;

  public function __construct(\Pimple $site) {
    $site->setDefaultConfigration('server.user', 'www-data');
    $site->setDefaultConfigration('server.webroot', '/var/www');
    $site->setDefaultConfigration('server.vhost_enabled_folder', '/etc/apache2/sites-enabled');
    $site->setDefaultConfigration('server.vhost_available_folder', '/etc/apache2/sites-available');
    $this->site = $site;
  }

  /**
   * Check whether this site appears to be enabled.
   *
   * TODO: This can vary based on the system.
   */
  public function siteEnabled() {
    return is_link($this->site['server.vhost_enabled_folder'] . '/' . $this->site['name']);
  }

  /**
   * Check whether this site appears to be configured and configure it if not.
   *
   * TODO: This can vary based on the system.
   */
  public function ensureSiteConfigured() {
    $site = $this->site;
    $vhostPath = $this->getVhostPath();
    if (!is_file($vhostPath)) {
      $vars = array(
        'site_name' => $site['name'],
        'hostname' => $site['hostname'],
        'site_folder' => $site['site.working_directory'],
      );
      $content = \drush_fetcher_get_asset('drupal.' . $site['version'] . '.vhost', $vars);
      $site['system']->writeFile($vhostPath, $content);
    }
  }

  /**
   * Get the path where vhost files should be placed.
   *
   * TODO: This can vary based on the system.
   */
  public function getVhostPath() {
    return $this->site['server.vhost_available_folder'] . '/' . $this->site['name'];
  }

  /**
   * Ensure that the site is removed.
   *
   * TODO: Vhost deletion can vary based on the system.
   */
  public function ensureSiteRemoved() {
    if ($this->siteEnabled()) {
      $this->ensureSiteDisabled();
      $this->restart();
    }
    $this->site['system']->ensureDeleted($this->getVhostPath());
  }

  /**
   * Ensure that the configured site has been enabled.
   *
   * TODO: This can vary based on the system.
   */
  public function ensureSiteEnabled() {
    $command = 'a2ensite ' . $this->site['name'];
    drush_log('Executing `' . $command . '`.');
    if (!drush_shell_exec($command)) {
      throw new \Fetcher\FetcherException(dt('The site @site could not be enabled.'), array('@site' => $this->site['name']));
    }
  }

  /**
   * Ensure that the configured site has been disabled.
   *
   * TODO: This can vary based on the system.
   */
  public function ensureSiteDisabled() {
    $command = 'a2dissite ' . $this->site['name'];
    drush_log('Executing `' . $command . '`.');
    if (!drush_shell_exec($command)) {
      throw new \Fetcher\FetcherException(dt('The site @site could not be disabled.', array('@site' => $this->site['name'])));
    }
  }

  /**
   * Restart the server to load the configuration.
   *
   * Note this should be done cracefully if possible.
   *
   * TODO: This can vary based on the system.
   */
  public function restart() {
    $command = 'sudo service apache2 reload';
    if (!drush_shell_exec($command)) {
      throw new \Fetcher\Exception\FetcherException(dt('Apache failed to restart, the server may be down.'));
    }
  }

}

