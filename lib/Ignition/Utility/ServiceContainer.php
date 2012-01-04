<?php

/**
 * @file
 *   This service container is based on [Pimple](https://github.com/fabpot/Pimple), a simple PHP
 *   Dependency Injection Container.  The goal of this file is 2 fold.  First we want to have a
 *   central clearinghouse for all context that may be populated by global context *or* manually
 *   constructed for use by Ignition or other integrating parties.
 */

namespace Ignition\Utility;
use \Pimple;

class ServiceContainer extends \Pimple {

  /**
   * This constructor function 
   *
   * A word of warning: most 
   */
  public function __construct() {

    // Register our service for generating a random string.
    $class = get_class($this);
    $this['random'] = function($c) use ($class) {
      return $class::randomString();
    };

    // The Ignition site class to use.
    $this['site class'] = '\Ignition\Site';

    // The default Site service loader.
    $this['site'] = $this->share(function($c) {
      $site = new $c['site class']($c);
      // TODO: Set up the site class?
      return $site;
    });

    // Set our default system to Ubuntu.
    $this['system class'] = '\Ignition\Server\Ubuntu';

    // Attempt to load a plugin appropriate to the system, defaulting to Ubuntu.
    $this['system'] = $this->share(function($c) {
      return new $c['system class']($c);
    });

    // Set our default server to Apache2.
    $this['server class'] = '\Ignition\Server\Apache2';

    // Attempt to load a plugin appropriate to the server, defaulting to Apache2.
    $this['server'] = $this->share(function($c) {
      return new $c['server class']($c);
    });
    
    // Set our default database to MySQL.
    $this['database class'] = '\Ignition\DB\MySQL';

    // Attempt to load a plugin appropriate to the database, defaulting to Mysql.
    $this['database'] = $this->share(function($c) {
      return new $c['database class']($c);
    });

    // Set our default VCS to Git.
    $this['vcs class'] = '\Ignition\VCS\Git';

    // Attempt to load a plugin appropriate to the VCS, defaulting to Git.
    $this['vcs'] = $this->share(function($c) {
      return new $c['vcs class']($c);
    });

    // Set our default ignition client class to our own HTTPClient.
    $this['ignition client class'] = '\Ignition\Utility\HTTPClient';

    // Set our default ignition client authentication class to our own HTTPClient.
    $this['ignition client authentication class'] = '\Ignition\Authentication\PublicKey';
  }

  /**
   * Create an Ignition ServiceContainer populated from the global Drush context.
   */
  static function getServiceContainerFromDrushContext() {

    $container = new static();

    // Detect overrides passed in as Drush options.
    if ($class = drush_get_option('ignition-system', FALSE)) {
      $container['system class'] =  '\\Ignition\\System\\' . $class;
    }
    if ($class = drush_get_option('ignition-database', FALSE)) {
      $container['database class'] = '\\Ignition\\DB\\' . $class;
    }
    if ($class = drush_get_option('ignition-server', FALSE)) {
      $container['server class'] = '\\Ignition\\Server\\' . $class;
    }
    if ($class = drush_get_option('ignition-vcs', FALSE)) {
      $container['vcs class'] = '\\Ignition\\VCS\\' . $class;
    }
   
    $container['ignition client'] = function($c) {
      if (!drush_get_option('ignition-server', FALSE)) {
        $message = 'The ignition server option must be set, we recommend setting it in your .drushrc.php file.';
        drush_log(dt($message), 'error');
        throw new \Ignition\Exception\IgnitionException($message);
      }
      $client = new $c['ignition client class']();
      $client->setURL(drush_get_option('ignition-host'))
        ->setMethod('GET')
        ->setTimeout(3)
        ->setEncoding('json');

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
    // TODO: This still doesn't quite feel right.  Should we load the db data here?
    // TODO: Move this global context...
    $this['dbSpec'] = array(
      'database' => drush_get_option('database', $siteInfo->name),
      'port' => drush_get_option('database-port', 3306),
      'username' => drush_get_option('database-user', $siteInfo->name),
      'password' => drush_get_option('database-password', $this['random']),
      'host' => 'localhost',
      'driver' => $this['database class']::getDriver(),
    );
    $this['site info'] = $siteInfo;
    return $this;
  }

  /**
   * Generate a random string.
   *
   * Essentially stolen from Drupal 7's `drupal_random_bytes`.
   */
  static public function randomString() {
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

