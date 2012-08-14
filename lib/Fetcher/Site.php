<?php

namespace Fetcher;
use \Symfony\Component\Yaml\Yaml;
use \Pimple;
use Symfony\Component\Process\Process;

class Site extends Pimple implements SiteInterface {

  // An multi-dimensional array of build hooks.
  protected $buildHooks = array();

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
        if (isset($environment['fetcher'])) {
          $copy = (array) $environment['fetcher'];
          array_walk_recursive($copy, function(&$value) {
            if (is_object($value) && get_class($value) == 'stdClass') {
              return (array) $value;
            }
            return $value;
          });
          $environment['fetcher'] = $copy;
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
      $content = \drush_fetcher_get_asset('drupal.' . $this['version'] . '.settings.php', $vars);

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
      if (in_array('Fetcher\CodeFetcher\SetupInterface', class_implements($this['code fetcher']))) {
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
   *
   * @param $operation
   *   The operation upon wh
   *    'initial' - 
   *    'before' - 
   *    'after' - 
   * @param $action
   *   This can be either a string to be executed at the command line or a
   *   Closure that accepts the site object as an argument.
   * @param $directory
   *   (Optional) Each hook is executed from inside the drupal code root.  If
   *   necessary a directory can be specified to run the command from.  This can
   *   either be a path relative to the Drupal root or an absolute path.
   */
  public function registerBuildHook($operation, $action, $directory = NULL) {
    if (empty($this->buildHooks[$operation])) {
      $this->buildHooks[$operation] = array();
    }
    $this->buildHooks[$operation] = array(
      'action' => $action,
      'directory' => $directory,
    );
  }

  /**
   *
   */
  public function getOperationBuildHooks($operation) {
    if (!empty($this->buildHooks[$operation])) {
      return $this->buildHooks[$operation];
    }
  }

  /**
   * Run all registered callbacks for an operation.
   */
  public function runOperationBuildHooks($operation) {
    if (!empty($this->buildHooks[$operation])) {
      $startingDir = getcwd();
      foreach ($this->buildHooks as $hook) {
        chdir($c['site.code_directory']);
        if (!empty($hook['directory'])) {
          chdir($hook['directory']);
        }
        if (is_string($hook['action'])) {
          $process = $this['process']($hook['action']);
          $this['log'](dt('Executing command: `@command`', array('@command' => $hook['action']), 'info'));
          $logger = NULL;
          if ($this['verbose']) {
            $logger = function ($type, $buffer) {
              print $buffer;
            };
          }
          $process->run($logger);
          if (!$process->isSuccessful()) {
            $message = 'Build hook failed: @hook';
            if ($errorOutput = $process->getErrorOutput() && !empty($errorOutput)) {
              $message .= 'and exited with "@error"';
            }
       #     throw new \Exception(dt($message, array('@hook' => $hook['action'], '@error' => $getErrorOutput)));
          }
        }
        else if (is_object($hook['action']) && get_class($hook['action']) == 'Closure') {
          try {
            $hook['action']($this);
          }
          catch (\Exception $e) {
            $this['log']('Build hook failed.', 'error');
          }
        }
      }
      chdir($startingDir);
    }
  }



  /**
   * Write a site info file from our siteInfo if it doesn't already exist.
   */
  public function ensureSiteInfoFileExists() {
    $conf = $this;
    // Simple Closure to recursively cast object to arrays.
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

    // Symlinks that need to be created.
    $this['symlinks'] = function ($c) {
      return array(
        $c['site.working_directory'] . '/public_files' => $c['site.code_directory'] . '/sites/default/files',
        $c['site.code_directory'] => $c['site.working_directory'] . '/webroot',
      );
    };

    $this['process'] = $this->protect(function() {
      $reflection = new \ReflectionClass('Symfony\Component\Process\Process'); 
      $process = $reflection->newInstanceArgs(func_get_args());
      return $process;
    });


    // If the log function is changed it must have the same function signature.
    $this['log function'] = 'drush_log';

    // We need a copy of site to close over in our closure.
    $site = $this;
    $this['log'] = $this->protect(function() use ($site) {
      $args = func_get_args();
      return call_user_func_array($site['log function'], $args);
    });
    unset($site);

    // Set our default system to Ubuntu.
    // TODO: Do some detection?
    $this['system class'] = '\Fetcher\System\Ubuntu';

    // Attempt to load a plugin appropriate to the system, defaulting to Ubuntu.
    $this['system'] = $this->share(function($c) {
      return new $c['system class']($c);
    });

    // Set our default server to Apache2.
    $this['server class'] = '\Fetcher\Server\Apache2';

    // Attempt to load a plugin appropriate to the server, defaulting to Apache2.
    $this['server'] = $this->share(function($c) {
      return new $c['server class']($c);
    });

    // Set our default database to MySQL.
    $this['database class'] = '\Fetcher\DB\Mysql';

    // Attempt to load a plugin appropriate to the database, defaulting to Mysql.
    $this['database'] = $this->share(function($c) {
      return new $c['database class']($c);
    });

    // Setup the administrative db credentials ().
    $this['database.admin.user'] = FALSE;
    $this['database.admin.password'] = FALSE;
    $this['database.admin.hostname'] = 'localhost';
    $this['database.admin.port'] = '';


    $this['database.username'] = function($c) { return $c['name']; };
    $this['database.database'] = function($c) { return $c['name']; };

    // TODO: For gotten sites maybe we load this from drush or the site info file?
    $this['database.hostname'] = 'localhost';
    $this['database.password'] = $this->share(function($c) {
      return $c['random'](); 
    });
    $this['database.driver'] = $this->share(function($c) {
      return $this['database class']::getDriver();
    });
    $this['database.port'] = 3306;



    // Set our default VCS to Git.
    $this['code fetcher class'] = '\Fetcher\CodeFetcher\VCS\Git';
    $this['code fetcher.config'] = array();

    // Attempt to load a plugin appropriate to the Code Fetcher, defaulting to Git.
    $this['code fetcher'] = $this->share(function($c) {
      $vcs = new $c['code fetcher class']($c);
      return $vcs;
    });

    // For most cases, the Drush sql-sync command can be used for synchronizing.
    $this['database synchronizer class'] = 'Fetcher\DBSynchronizer\DrushSqlSync';

    $this['database synchronizer'] = $this->share(function($c) {
      return new $c['database synchronizer class']($c);
    });

    // Usually set by the drush option.
    // If set print logs but take no action.
    $this['simulate'] = FALSE;

    // Usually set by drush option.
    // Prints more verbose logs.
    $this['verbose'] = FALSE;

    // The hostname of the system.
    $this['system hostname'] = function ($c) {
      return $c['system']->getHostname();
    };

    // The URI of the site.
    $this['hostname'] = function($c) {
      return strtolower($c['name'] . '.' . $c['system hostname']);
    };

    // TODO: This is retarderated:
    // TODO: Add optional webroot from siteInfo.
    $this['site.working_directory'] = function($c) {
      return $c['server']->getWebroot() . '/' . $c['name'];
    };

    // Some systems place the Drupal webroot in a subdirectory.
    // This option configures the name of the subdirectory (some use htdocs).
    $this['webroot subdirectory'] = '';

    // The directory inside the working directory to place the drupal code.
    // Note the Drupal root may be in a subdirectory, see 'webroot subdirectory'.
    $this['site.code_directory'] = function($c) {
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
   * Configure the service container with site information loaded from a class
   * implementing Fetcher\InfoFetcherInterface.
   *
   * @param $siteInfo
   *   The information returned from `\drush_fetcher_get_site_info()`.
   * TODO: Deprecate this in favor of a constructor the receives an alias.
   */
  public function configureWithSiteInfo($siteInfo) {
    if (isset($site_info->vcs)) {
      $this['code fetcher'] = $this->share(function() {
        drush_fetcher_get_handler('code fetcher', $site_info->vcs);
      });
    }

    // Load the site variables.
    $this['name'] = $siteInfo->name;

    $fetch_config = array();
    if (isset($siteInfo->vcs_url)) {
      $fetch_config['url'] = trim($siteInfo->vcs_url);
    }

    // Load the environment variables.
    // TODO: Make this configurable
    $this['remote.url'] = trim($siteInfo->environments->dev->server->hostname);

    if (isset($siteInfo->environments->dev->fetcher->branch)) {
      $fetch_config['branch'] = trim($siteInfo->environments->dev->fetcher->branch);
    }
    else {
      $fetch_config['branch'] = 'master';
    }

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
