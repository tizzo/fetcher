<?php

/**
 * @file
 *   This service container is based on [Pimple](https://github.com/fabpot/Pimple), a simple PHP
 *   Dependency Injection Container.
 */

namespace Ignition\Utility;
use \Pimple;

class ServiceContainer extends \Pimple {

  public function __construct() {


    $this['random'] = function($c) {
      return static::randomString();
    };

    $this['site class'] = '\Ignition\Site';

    $this['site'] = $this->share(function($c) {
      return new $this['site class']($c);
    });
  }

  /**
   * Create an Ignition ServiceContainer populated from the global Drush context.
   */
  static function getServiceContainerFromDrushContext() {

    $container = new static();

    $container['client'] = function($c) {
      if (!drush_get_option('ignition-server', FALSE)) {
        // TODO: Replace with an exception.
        drush_log('The ignition server option must be set, recommend setting it in your .drushrc.php file.', 'error');
        return FALSE;
      }
      // TODO: Add authentication.
      $client->setURL(drush_get_option('ignition-host'))
        ->setMethod('GET')
        ->setTimeout(3)
        ->setFormat('json');
      return $client;
    };

    // Attempt to load a plugin appropriate to the system, defaulting to Ubuntu.
    $container['system'] = $container->share(function($c) {
      return drush_ignition_get_handler('System', drush_get_option('ignition-system', 'Ubuntu', $c)); 
    });

    // Attempt to load a plugin appropriate to the server, defaulting to Apache2.
    $container['server'] = $container->share(function($c) {
      return drush_ignition_get_handler('Server', drush_get_option('ignition-server', 'Apache2', $c));
    });

    // Attempt to load a plugin appropriate to the database, defaulting to Mysql.
    $container['database'] = $container->share(function($c) {
      return drush_ignition_get_handler('DB', drush_get_option('ignition-database', 'Mysql'), $c);
    });

    // Attempt to load a plugin appropriate to the VCS, defaulting to Git.
    $container['vcs'] = $container->share(function($c) {
      return drush_ignition_get_handler('VCS', 'Git', $c);
    });

    // TODO: This still doesn't feel right.  We shouldn't load the db data here.
    $container['dbSpec'] = array(
      'database' => drush_get_option('database', $site_info->name),
      'port' => drush_get_option('database-port', 3306),
      'username' => drush_get_option('database-user', $site_info->name),
      'password' => drush_get_option('database-password', $this['random']),
      'host' => 'localhost',
      'driver' => $container['database']->getDriver(),
    );

    $container['ignition client class'] = '\Ignition\Utility\HTTPClient';

    // TODO: Finish making this pluggable.
    //$container['ignition client authentication class'] = '\Ignition\Authentication\PublicKey';

    $container['ignition client'] = function($c) {
      $client = new $c['ignition client class']();
      $client->setURL(drush_get_option('ignition-host'))
        // TODO: Add authentication.
        ->setMethod('GET')
        ->setTimeout(3)
        ->setFormat('json');

      // TODO: Implement this.
      //$container['ignition client authentication class']::addAuthenticationToHTTPClientFromDrushContext($client);

      return $client;
    };

    return $container;
  }

  /**
   * Configure the service container with site information loaded from Ignition.
   *
   * @param $siteInfo
   *   The information returned from `\drush_ignition_get_site_info()`.
   */
  public function configureWithSiteInfo($siteInfo) {
    if (isset($site_info->vcs)) {
      $this['vcs'] = $this->share(function() {
        drush_ignition_get_handler('VCS', $site_info->vcs);
      });
    }
    $this['site info'] = $siteInfo;
    return $this;
  }

  /**
   * Generate a random string.
   *
   * Essentially stolen from Drupal 7's `drupal_random_bytes`.
   */
  static private function randomString() {
    $count = 55;
    // $random_state does not use drupal_static as it stores random bytes.
    static $random_state, $bytes;
    // Initialize on the first call. The contents of $_SERVER includes a mix of
    // user-specific and system information that varies a little with each page.
    if (!isset($random_state)) {
      $random_state = print_r($_SERVER, TRUE);
      if (function_exists('getmypid')) {
        // Further initialize with the somewhat random PHP process ID.
        $random_state .= getmypid();
      }
      $bytes = '';
    }
    if (strlen($bytes) < $count) {
      if ($fh = @fopen('/dev/urandom', 'rb')) {
        $bytes .= fread($fh, max(4096, $count));
        fclose($fh);
      }
      while (strlen($bytes) < $count) {
        $random_state = hash('sha256', microtime() . mt_rand() . $random_state);
        $bytes .= hash('sha256', mt_rand() . $random_state, TRUE);
      }
    }
    $data = substr($bytes, 0, $count);
    $bytes = substr($bytes, $count);
    $hash = base64_encode(hash('sha256', $data, TRUE));
    return strtr($hash, array('+' => '-', '/' => '_', '=' => ''));
  }

}

