<?php

namespace Ignition\Server;

class Apache2 {
  
  protected $container;

  public function __construct(\Pimple $container) {
    $this->container = $container;
  }

  public function getWebUser() {
    return 'www-data';
  }

  public function getWebRoot() {
    return '/var/www';
  }

  public function siteEnabled() {
    return is_link('/etc/apache2/sites-enabled/' . $this->container['site.name']);
  }

  public function ensureSiteConfigured() {
    $container = $this->container;
    $vhostPath = $this->getVhostPath();
    if (!is_file($vhostPath)) {
      $vars = array(
        'site_name' => $container['site.name'],
        'hostname' => $container['site.hostname'],
        'site_folder' => $container['site.working_directory'],
      );
      $content = \drush_ignition_get_asset('drupal.' . $container['version'] . '.vhost', $vars);
      $container['system']->writeFile($vhostPath, $content);
    }
  }

  public function getVhostPath() {
    return '/etc/apache2/sites-available/' . $this->container['site.name'];
  }

  public function ensureSiteRemoved() {
    if ($this->siteEnabled()) {
      $this->ensureSiteDisabled();
      $this->restart();
    }
    $this->container['system']->ensureDeleted($this->getVhostPath());
  }

  public function ensureSiteEnabled() {
    $command = 'a2ensite ' . $this->container['site.name'];
    drush_log('Executing `' . $command . '`.');
    if (!drush_shell_exec($command)) {
      throw new \Ignition\IgnitionException(dt('The site @site could not be enabled.'), array('@site' => $this->container['site.name']));
    }
  }

  public function ensureSiteDisabled() {
    $command = 'a2dissite ' . $this->container['site.name'];
    drush_log('Executing `' . $command . '`.');
    if (!drush_shell_exec($command)) {
      throw new \Ignition\IgnitionException(dt('The site @site could not be disabled.', array('@site' => $this->container['site.name'])));
    }
  }

  public function restart() {
    $command = 'sudo service apache2 restart';
    if (!drush_shell_exec($command)) {
      throw new \Ignition\Exception\IgnitionException(dt('Apache failed to restart, the server may be down.'));
    }
  }

}

