<?php

namespace Fetcher\Server;

class Apache2 {

  protected $site;

  public function __construct(\Pimple $site) {
    $site->setDefaultConfigration('server.user', 'www-data');
    $site->setDefaultConfigration('server.port', 80);
    $site->setDefaultConfigration('server.webroot', '/var/www');
    $site->setDefaultConfigration('server.vhost_enabled_folder', '/etc/apache2/sites-enabled');
    $site->setDefaultConfigration('server.vhost_available_folder', '/etc/apache2/sites-available');
    $site->setDefaultConfigration('server.restart_command', 'sudo service apache2 reload');
    $site->setDefaultConfigration('server.enable_site_command', function($c) {
      return 'a2ensite ' . $c['name'];
    });
    $site->setDefaultConfigration('server.disable_site_command', function($c) {
      return 'a2dissite ' . $c['name'];
    });
    $this->site = $site;
  }

  /**
   * Implements \Fetcher\Server\ServerInterface::registerSettings().
   *
   * TODO: I think this is a good idea...
   */
  static public function registerSettings(\Fetcher\Site $site) {
    $site->setDefaultConfigration('server.user', 'www-data');
    $site->setDefaultConfigration('server.basewebroot', '/var/www');
  }

  /**
   * Get the user under which this server runs.
   *
   * TODO: This can vary based on the system.
   */
  public function getWebUser() {
    return 'www-data';
  }

  /**
   * Get the parent folder where web files should be located.
   *
   * TODO: This can vary based on the system.
   */
  public function getWebRoot() {
    return '/var/www';
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
        'docroot' => $site['site.webroot'],
        'port' => $site['server.port'],
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
    $site = $this->site;
    $site['log'](\sprintf('Executing `%s`.', $site['server.enable_site_command']));
    if (!$site['simulate']) {
      $process = $site['process']($site['server.enable_site_command']);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new \Fetcher\Exception\FetcherException(\sprintf('The site %s could not be enabled.', $this->site['name']));
      }
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
    if (!drush_shell_exec($this->site['server.restart_command'])) {
      throw new \Fetcher\Exception\FetcherException(dt('Apache failed to restart, the server may be down.'));
    }
  }

}

