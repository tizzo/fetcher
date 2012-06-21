<?php

namespace Ignition;
use \Symfony\Component\Yaml\Yaml;
use \Pimple;

class Site extends Pimple implements SiteInterface {

  /**
   * Constructor function to allow dependency injection.
   *
   */
  public function __construct() {
    // Populate defaults.
    $this->setDefaults();
  }

  /**
   * Ensure the database exists, the user exists and the user can connect.
   */
  public function ensureDatabase() {
    if (!$this['database']->exists()) {
      $this['database']->createDatabase();
    }
    if (!$this['database']->userExists()) {
      $name = $this['site.info']->name;
      $this['database']->createUser();
      $this['database']->grantAccessToUser();
    }
  }

  /**
   * Build the drush alias and place it in the home folder.
   */
  public function ensureDrushAlias() {
    $drushPath = $this['system']->getUserHomeFolder() . '/.drush';
    $this['system']->ensureFolderExists($drushPath);
    $drushFilePath = $this->getDrushAliasPath();
    if (!is_file($drushFilePath)) {
      $content = '';
      $content = "<?php" . PHP_EOL;
      $environments = (array) $this['site.info']->environments;
      $environments['local'] = array(
        'uri' => $this['hostname'],
        // TODO: We use this in other places so this should be an element in our container config.
        'root' => $this['site.working_directory'] . '/webroot',
      );
      foreach ($environments as $name =>  $environment) {
        $environment = (array) $environment;
        $string = '';
        if (isset($environment['ignition'])) {
          $copy = (array) $environment['ignition'];
          array_walk_recursive($copy, function(&$value) {
            if (get_class($value) == 'stdClass') {
              return (array) $value;
            }
            return $value;
          });
          $environment['ignition'] = $copy;
        }
        $content .= "\$aliases['$name'] = " . $this->arrayExport($environment, $string, 0) . ";" . PHP_EOL;
      }
      $this['system']->writeFile($drushFilePath, $content);
    }
  }

  /**
   * Setup our basic working directory.
   */
  public function ensureWorkingDirectory() {

    // Ensure we have our working directory.
    $this['system']->ensureFolderExists($this['site.working_directory']);

    // Ensure we have a log directory.
    $this['system']->ensureFolderExists($this['site.working_directory'] . '/logs');

    // Ensure we have our log files.
    // TODO: We probably only want these on dev.
    $this['system']->ensureFileExists($this['site.working_directory'] . '/logs/access.log');
    $this['system']->ensureFileExists($this['site.working_directory'] . '/logs/mail.log');
    $this['system']->ensureFileExists($this['site.working_directory'] . '/logs/watchdog.log');

    // Ensure we have our files folders.
    $this['system']->ensureFolderExists($this['site.working_directory'] . '/public_files', NULL, $this['server']->getWebUser());
    if (isset($this['site.info']->{'private files'})) {
      $this['system']->ensureFolderExists($this['site.working_directory'] . '/private_files', NULL, $this['server']->getWebUser());
    }

  }

  /**
   * Checks to see whether settings.php exists and creates it if it does not.
   */
  public function ensureSettingsFileExists() {
    // TODO: Support multisite?
    // TODO: This is ugly, what we're doing with this container here...
    $settingsFilePath = $this['site.code_directory'] . '/sites/default/settings.php';
    if (!is_file($settingsFilePath)) {
      $conf = $this;
      $vars = array();
      $vars =  array(
        'database' => $conf['database.database'],
        'hostname' => $conf['database.hostname'],
        'username' => $conf['database.username'],
        'password' => $conf['database.password'],
        'driver' => $conf['database.driver'],
      );
      // TODO: Get the settings.php for the appropriate version.
      $content = \drush_ignition_get_asset('drupal.' . $this['version'] . '.settings.php', $vars);

      // If we have a site-settings.php file for this site, add it here.
      if (is_file($this['site.code_directory'] . '/sites/default/site-settings.php')) {
        $content .= PHP_EOL . "require_once('site-settings.php');" . PHP_EOL;
      }
      $this['system']->writeFile($settingsFilePath, $content);
    }
  }


  /**
   * Ensure the code is in place.
   */
  public function ensureCode() {
    if (!is_dir($this['site.code_directory'])) {
      $this['code fetcher']->setup();
    }
    else {
      // If the code fetcher supports updating already fetched code, update the code.
      if (in_array('\Ignition\CodeFetcher\SetupInterface', class_implements($this['code fetcher']))) {
        $this['code fetcher']->update();
      }
    }
    // If our webroot is in a configured subdirectory, use that for the root.
    if (is_dir($this['site.code_directory'] . '/' . $this['webroot subdirectory'])) {
      $this['site.code_directory'] = $this['site.code_directory'] . '/' . $this['webroot subdirectory'];
    }
    else {
      $this['site.code_directory'] = $this['site.code_directory'];
    }
  }

  /**
   * Ensure that all symlinks besides the webroot symlink have been created.
   */
  public function ensureSymLinks() {
    foreach ($this['symlinks'] as $realPath => $symLink) {
      $this['system']->ensureSymLink($realPath, $symLink);
    }
  }

  /**
   * Ensure the site has been added to the appropriate server (e.g. apache vhost).
   */
  public function ensureSiteEnabled() {
    $server = $this['server'];
    if (!$server->siteEnabled()) {
      $server->ensureSiteConfigured();
      $server->ensureSiteEnabled();
      $server->restart();
    }
  }

  /**
   * Synchronize the database with a remote environment.
   */
  public function syncDatabase() {
    return $this['database synchronizer']->syncDB();
  }

  /**
   * Calculate the drush alias path.
   */
  public function getDrushAliasPath() {
    return $this['system']->getUserHomeFolder() . '/.drush/' . $this['site.info']->name . '.aliases.drushrc.php';
  }

  /**
   * Removes all traces of this site from this system.
   */
  public function remove() {
    $this['system']->ensureDeleted($this['site.working_directory']);
    $this['system']->ensureDeleted($this->getDrushAliasPath());
    //$this['system']->removeSite($this['site.info']->name);
    if ($this['database']->exists()) {
      $this['database']->removeDatabase();
    }
    if ($this['database']->userExists()) {
      $this['database']->removeUser();
    }
    $this['server']->ensureSiteRemoved();
  }

  /**
   * Write a site info file from our siteInfo if it doesn't already exist.
   */
  public function ensureSiteInfoFileExists() {
    $conf = $this;
    // Simple Closure to convert recursively cast object to arrays.
    // TODO: Oh god, if we ever have an object in here life is a world of pain.
    $recursiveCaster = function($item) use (&$recursiveCaster) {
      if (is_object($item)) {
        $item = (array) $item;
      }
      foreach ($item as $name => $value) {
        if (is_object($value)) {
          $item[$name] = $recursiveCaster($value);
        }
      }
      return $item;
    };
    $siteInfo = $this['site.info'];
    $string = Yaml::dump($recursiveCaster($siteInfo), 5);
    $this['system']->writeFile($this['site.working_directory'] . '/site_info.yaml', $string);
  }

  /**
   * Parse site info from a string.
   */
  static public function parseSiteInfo($string) {
    $info = Yaml::parse($string);
    $info = (object) $info;
    // TODO: We should prolly turn this into an array in the importer.
    return $info;
  }

  /**
   * Populate this object with defaults.
   */
  public function setDefaults() {

    $this['symlinks'] = function ($c) {
      return array(
        $c['site.working_directory'] . '/public_files' => $c['site.code_directory'] . '/sites/default/files',
        $c['site.code_directory'] => $c['site.working_directory'] . '/webroot',
      );
    };

    // Set our default system to Ubuntu.
    // TODO: Do some detection?
    $this['system class'] = '\Ignition\System\Ubuntu';

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
    $this['database class'] = '\Ignition\DB\Mysql';

    // Attempt to load a plugin appropriate to the database, defaulting to Mysql.
    $this['database'] = $this->share(function($c) {
      return new $c['database class']($c);
    });

    // Set our default VCS to Git.
    $this['code fetcher class'] = '\Ignition\CodeFetcher\VCS\Git';
    $this['code fetcher.config'] = array();

    // Attempt to load a plugin appropriate to the Code Fetcher, defaulting to Git.
    $this['code fetcher'] = $this->share(function($c) {
      $config = array();
      $config['codeDirectory'] = $c['site.code_directory'];
      $vcs = new $c['code fetcher class']($c);
      return $vcs;
    });

    // For most cases, the Drush sql-sync command can be used for synchronizing.
    $this['database synchronizer class'] = 'Ignition\DBSynchronizer\DrushSqlSync';

    // Load the configured db synchronizer.
    $this['database synchronizer'] = $this->share(function($c) {
      return new $c['database synchronizer class']($c);
    });

    // Instantiate the authentication object.
    $this['client.authentication'] = $this->share(function($c) {
      return new $c['client.authentication class']($c);
    });

    $this['simulate'] = FALSE;
    $this['verbose'] = FALSE;

    $this['system hostname'] = function ($c) {
      return $c['system']->getHostname();
    };

    $this['hostname'] = function($c) {
      return strtolower($c['site.name'] . '.' . $c['system hostname']);
    };

    // TODO: This is retarderated:
    // TODO: Add optional webroot from siteInfo.
    $this['site.working_directory'] = function($c) {
      return $c['server']->getWebroot() . '/' . $c['site.name'];
    };

    // Some systems place the Drupal webroot in a subdirectory.  This option configures that subdirectory.
    $this['webroot subdirectory'] = 'webroot';

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
   * Configure the service container with site information loaded from Ignition.
   *
   * @param $siteInfo
   *   The information returned from `\drush_ignition_get_site_info()`.
   * TODO: Deprecate this in favor of a constructor the receives an alias.
   */
  public function configureWithSiteInfo($siteInfo) {
    if (isset($site_info->vcs)) {
      $this['code fetcher'] = $this->share(function() {
        drush_ignition_get_handler('code fetcher', $site_info->vcs);
      });
    }

    // Load the site variables.
    $this['site.name'] = $siteInfo->name;

    $fetch_config = array();
    if (isset($siteInfo->vcs_url)) {
      $fetch_config['url'] = trim($siteInfo->vcs_url);
    }

    // Load the environment variables.
    // TODO: Make this configurable
    $this['remote.url'] = trim($siteInfo->environments->dev->server->hostname);

    if (isset($siteInfo->environments->dev->ignition->branch)) {
      $fetch_config['branch'] = trim($siteInfo->environments->dev->ignition->branch);
    }
    else {
      $fetch_config['branch'] = 'master';
    }
    $this['code fetcher.config'] = $fetch_config;

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
    // TODO: Add support for remote db servers.
    $this['database.username'] = drush_get_option('database-user',  function($c) { return $c['site.name']; });
    $this['database.database'] = drush_get_option('database', function($c) { return $c['site.name']; });

    // TODO: Where should this go, it doesn't touch site info:
    $this['database.hostname'] = 'localhost';
    $this['database.password'] = drush_get_option('database-password', $this['random']());
    $this['database.driver'] = $this['database class']::getDriver();
    $this['database.port'] = drush_get_option('database-port', 3306);

    $this['version'] = $siteInfo->environments->dev->ignition->version;

    $this['simulate'] = drush_get_context('DRUSH_SIMULATE');
    $this['verbose'] = drush_get_context('DRUSH_VERBOSE');

    $this['site.info'] = $siteInfo;
    return $this;
  }

  /**
   * Export an array as executable PHP code.
   *
   * @param (Array) $data
   *  The array to be exported.
   * @param (string) $string
   *  The string to add to this array to.
   * @param (int) $indentLevel
   *  The level of indentation this should be run at.
   */
  public function arrayExport(Array $data, &$string, $indentLevel) {
    $i = 0;
    $indent = '';
    while ($i < $indentLevel) {
      $indent .= '  ';
      $i++;
    }
    $string .= "array(" . PHP_EOL;
    foreach ($data as $name => $value) {
      $string .= "$indent  '$name' => ";
      if (is_array($value)) {
        $inner_string = '';
        $string .= $this->arrayExport($value, $inner_string, $indentLevel + 1) . "," . PHP_EOL;
      }
      else if (is_numeric($value)) {
        $string .= "$value," . PHP_EOL;
      }
      elseif (is_string($value)) {
        $string .= "'" . str_replace("'", "\'", $value) . "'," . PHP_EOL;
      }
      else {
        $string .= serialize($value);
      }
    }
    $string .= "$indent)";
    return $string;
  }
}
