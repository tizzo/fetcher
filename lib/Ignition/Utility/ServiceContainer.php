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
  
    // The Ignition site class to use.
    $this['site class'] = '\Ignition\Site';

    // The default Site service loader.
    $this['site'] = $this->share(function($c) {
      $site = new $c['site class']($c);
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
      $config = array();
      $config['codeDirectory'] = $c['site.code_directory'];
      if (isset($c['vcs.url'])) {
        $config['vcsURL'] = $c['vcs.url'];
      }
      $vcs = new $c['vcs class']($c);
      $vcs->configure($config);
      return $vcs;
    });

    // Set our default ignition client class to our own HTTPClient.
    $this['ignition client class'] = '\Ignition\Utility\HTTPClient';

    // Set our default ignition client authentication class to our own HTTPClient.
    $this['client.authentication class'] = '\Ignition\Authentication\OpenSshKeys';

    // Instantiate the authentication object.
    $this['client.authentication'] = $this->share(function($c) {
      return new $c['client.authentication class']($c);
    });

    $this['simulate'] = FALSE;
    $this['verbose'] = FALSE;

    // TODO: use context to build hostname.
    $this['site.hostname'] = function($c) {
      return $c['site.name'] . '.local';
    };

    // TODO: This is retarderated:
      // TODO: Add optional webroot from siteInfo.
    $this['site.working_directory'] = function($c) {
      return $c['server']->getWebroot() . '/' . $c['site.name'];
    };

    $this['site.code_directory'] = function($c) {
      // TODO: This needs to be smarter:
      return $c['site.working_directory'] . '/' . 'code';
    };

    /**
     * Generate a random string.
     *
     * Essentially stolen from Drupal 7's `drupal_random_bytes`.
     */
    // Register our service for generating a random string.
    $this['random'] = $this->protect(
      function($count = 20) {
        static $random_state, $bytes;
        if (!isset($random_state)) {
          $random_state = print_r($_SERVER, TRUE);
          if (function_exists('getmypid')) {
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
        $output = substr($bytes, 0, $count);
        $bytes = substr($bytes, $count);
        return base64_encode(substr(strtr($output, array('+' => '-', '/' => '_', '\\' => '_', '=' => '')), 0, -2));
      }
    );

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

      // Populate this object with the appropriate authorization credentials.
      $c['client.authentication']->addAuthenticationToHTTPClientFromDrushContext($client);

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

    // Load the site variables.
    $this['site.name'] = $siteInfo->name;

    if (isset($siteInfo->vcs_url)) {
      $this['vcs.url'] = $siteInfo->vcs_url;
    }

    // Load the environment variables.
    // TODO: Replace with environment!
    $this['remote.name'] = $siteInfo->environments->dev->server->name;
    $this['remote.url'] = $siteInfo->environments->dev->server->hostname;

    // Setup the administrative db credentials ().
    $this['database.admin.user'] = drush_get_option('ignition-db-username', FALSE);
    $this['database.admin.password'] = drush_get_option('ignition-db-username', FALSE);
    $this['database.admin.hostname'] = drush_get_option('ignition-db-username', 'localhost');
    $this['database.admin.port'] = drush_get_option('ignition-db-username', '');

    // TODO: If we're dealing with an already "gotten" site, we need to load the db_spec via drush
    // rather than reading context options.
    // TODO: When implementing the above, decide which should take precedence.
    // Setup the site specific db credentails.
    // TODO: Add support for this in siteInfo.
    $this['database.hostname'] = 'localhost';
    $this['database.username'] = drush_get_option('database-user', $siteInfo->name);
    $this['database.password'] = drush_get_option('database-password', $this['random']());
    $this['database.driver'] = $this['database class']::getDriver();
    $this['database.port'] = drush_get_option('database-port', 3306);
    $this['database.database'] = drush_get_option('database', $siteInfo->name);

    // Drop the first character because our versions are formatted d*.
    if (isset($siteInfo->version)) {
      $this['version'] = substr($siteInfo->version, 1);
    }

    $this['simulate'] = drush_get_context('DRUSH_SIMULATE');
    $this['verbose'] = drush_get_context('DRUSH_VERBOSE');

    $this['site info'] = $siteInfo;
    return $this;
  }

}

