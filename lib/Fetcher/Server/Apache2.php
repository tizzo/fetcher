<?php

namespace Fetcher\Server;

class Apache2 implements ServerInterface {

  protected $site;

  public function __construct(\Pimple $site) {
    $site->setDefaultConfigration('server.disable_site_command', function($c) {
      return 'sudo a2dissite ' . $c['name'] . '.conf';
    });
    $site->setDefaultConfigration('server.enable_site_command', function($c) {
      return 'sudo a2ensite ' . $c['name'] . '.conf';
    });
    $site->setDefaultConfigration('server.host_conf_path', function($c) {
      return $c['server.vhost_available_folder'] . '/' . $c['name'] . '.conf';
    });
    $site->setDefaultConfigration('server.fpm_url', '127.0.0.1:9000');
    $site->setDefaultConfigration('server.port', 80);
    $site->setDefaultConfigration('server.restart_command', 'sudo service apache2 reload');
    $site->setDefaultConfigration('server.sapi', 'mod_php');
    $site->setDefaultConfigration('server.user', 'www-data');
    $site->setDefaultConfigration('server.vhost_enabled_folder', '/etc/apache2/sites-enabled');
    $site->setDefaultConfigration('server.vhost_available_folder', '/etc/apache2/sites-available');
    $site->setDefaultConfigration('server.webroot', '/var/www');
    $this->site = $site;
  }

  /**
   * Implements \Fetcher\Server\ServerInterface::registerSettings().
   */
  static public function registerSettings(\Fetcher\Site $site) {
    $site->setDefaultConfigration('server.user', 'www-data');
    $site->setDefaultConfigration('server.basewebroot', '/var/www');
  }

  /**
   * Get the user under which this server runs.
   */
  public function getWebUser() {
    return 'www-data';
  }

  /**
   * Get the parent folder where web files should be located.
   */
  public function getWebRoot() {
    return '/var/www';
  }

  /**
   * Check whether this site appears to be enabled.
   */
  public function siteIsEnabled() {
    return is_link($this->site['server.vhost_enabled_folder'] . '/' . $this->site['name']);
  }

  /**
   * Check whether this site appears to be configured and configure it if not.
   */
  public function ensureSiteConfigured() {
    $site = $this->site;
    if (!is_file($site['server.host_conf_path'])) {
      $vars = array(
        'site_name' => $site['name'],
        'hostname' => $site['hostname'],
        'docroot' => $site['site.webroot'],
        'port' => $site['server.port'],
				'fpm_url' => $site['server.fpm_url'],
      );
      $content = \drush_fetcher_get_asset('apache.drupal.vhost.' . $site['server.sapi'], $vars);
      $site['system']->writeFile($site['server.host_conf_path'], $content);
    }
  }

  /**
   * Ensure that the site is removed.
   */
  public function ensureSiteRemoved() {
    if ($this->siteIsEnabled()) {
      $this->ensureSiteDisabled();
      $this->restart();
    }
    $this->site['system']->ensureDeleted($site['server.host_conf_path']);
  }

  /**
   * Ensure that the configured site has been enabled.
   */
  public function ensureSiteEnabled() {
    $site = $this->site;
    $site['log'](\sprintf('Executing `%s`.', $site['server.enable_site_command']));
    if (!$site['simulate']) {
      $process = $site['process']($site['server.enable_site_command']);
      $process->setTimeout(NULL);
      $process->run();
      if (!$process->isSuccessful()) {
        throw new \Fetcher\Exception\FetcherException(\sprintf('The site %s could not be enabled.', $this->site['name']));
      }
    }
  }

  /**
   * Ensure that the configured site has been disabled.
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
   */
  public function restart() {
    if (!drush_shell_exec($this->site['server.restart_command'])) {
      throw new \Fetcher\Exception\FetcherException(dt('Apache failed to restart, the server may be down.'));
    }
  }

}

