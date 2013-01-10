<?php

namespace Fetcher;
use \Symfony\Component\Yaml\Yaml;
use \Pimple;
use Symfony\Component\Process\Process;

class Site extends Pimple implements SiteInterface {

  // A multi-dimensional array of build hooks.
  protected $buildHooks = array();

  /**
   * Constructor function to populate the dependency injection container.
   */
  public function __construct($siteInfo = NULL) {
    // Populate defaults.
    $this->setDefaults();
    if (!empty($siteInfo)) {
      $this->configureWithSiteInfo($siteInfo);
    }
  }

  /**
   * Ensure the database exists, the user exists, and the user can connect.
   */
  public function ensureDatabase() {
    if (!$this['database']->canConnect()) {
      if (!$this['database']->exists()) {
        $this['database']->createDatabase();
      }
      if (!$this['database']->userExists()) {
        $name = $this['name'];
        $this['database']->createUser();
        $this['database']->grantAccessToUser();
      }
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
      if (isset($this['environments'])) {
        $environments = (array) $this['environments'];
      }
      else {
        $environments = array();
      }
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
    // TODO: Should we have an option for whether to create private files or not?
    $this['system']->ensureFolderExists($this['site.working_directory'] . '/private_files', NULL, $this['server']->getWebUser());
  }

  /**
   * Checks to see whether settings.php exists and creates it if it does not.
   */
  public function ensureSettingsFileExists() {
    // TODO: Support multisite?
    // TODO: This is ugly, what we're doing with this container here...
    $settingsFilePath = $this['site.code_directory'] . '/sites/default/settings.php';
    // If the settings file does not exist, create a new one.
    if (!is_file($settingsFilePath)) {
      $conf = $this;
      $vars = array();
      $vars =  array(
        'database' => $conf['database.database'],
        'hostname' => $conf['database.hostname'],
        'username' => $conf['database.user.name'],
        'password' => $conf['database.user.password'],
        'driver' => $conf['database.driver'],
        'environment-local' => $conf['environment.local'],
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
      $this['code_fetcher']->setup();
    }
    else {
      // If the code fetcher supports updating already fetched code, update the
      // code.
      if (in_array('Fetcher\CodeFetcher\UpdateInterface', class_implements($this['code_fetcher']))) {
        $this['code_fetcher']->update();
      }
    }
    // If our webroot is in a configured subdirectory, use that for the root.
    if (is_dir($this['site.code_directory'] . '/' . $this['webroot_subdirectory'])) {
      $this['site.code_directory'] = $this['site.code_directory'] . '/' . $this['webroot_subdirectory'];
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
   * Synchronize the files with a remote environment.
   */
  public function syncFiles($type) {
    return $this['file synchronizer']->syncFiles($type);
  }

  /**
   * Calculate the drush alias path.
   */
  public function getDrushAliasPath() {
    return $this['system']->getUserHomeFolder() . '/.drush/' . $this['name'] . '.aliases.drushrc.php';
  }

  /**
   * Removes all traces of this site from this system.
   */
  public function remove() {
    $this['system']->ensureDeleted($this['site.working_directory']);
    $this['system']->ensureDeleted($this->getDrushAliasPath());
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
        chdir($this['site.code_directory']);
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
    $conf = array();
    foreach ($this->keys() as $key) {
      $value = $this[$key];
      if (!is_object($value) || get_class($value) == 'stdClass') {
        $conf[$key] = $value;
      }
    }
    $string = Yaml::dump($conf, 5, 2);
    $this['system']->writeFile($this['site.working_directory'] . '/site_info.yaml', $string);
  }

  /**
   * Parse site info from a string.
   */
  static public function parseSiteInfo($string) {
    $info = Yaml::parse($string);
    $info = $info;
    return $info;
  }

  /**
   * Load the site_info array from the YAML file.
   */
  public function getSiteInfoFromInfoFile() {
    $path = $this['site.working_directory'] . '/site_info.yaml';
    if (is_file($path)) {
      $yaml = file_get_contents($path);
      $info = $this->parseSiteInfo($yaml);
      return $info;
    }
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

    $this['database.driver'] = $this->share(function($c) {
      return $c['database class']::getDriver();
    });

    $this['code_fetcher.vcs_mapping'] = array(
      'git' => 'Fetcher\CodeFetcher\VCS\Git',
    );

    // Set our default code fetcher class to drush download.
    $this['code_fetcher.class'] = 'Fetcher\CodeFetcher\Download';
    $this['code_fetcher.config'] = array();

    // Attempt to load a plugin appropriate to the Code Fetcher, defaulting to Git.
    $this['code_fetcher'] = $this->share(function($c) {
      $vcs = new $c['code_fetcher.class']($c);
      return $vcs;
    });

    // For most cases, the Drush sql-sync command can be used for synchronizing.
    $this['database synchronizer class'] = 'Fetcher\DBSynchronizer\DrushSqlSync';

    $this['database synchronizer'] = $this->share(function($c) {
      return new $c['database synchronizer class']($c);
    });

    // For most cases, RSync is file for file synchronizing. We'll find the
    // path to the files via drush.
    $this['file synchronizer class'] = 'Fetcher\FileSynchronizer\RsyncFileSync';

    $this['file synchronizer'] = $this->share(function($c) {
      return new $c['file synchronizer class']($c);
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

    // TODO: This is not the best way to do this:
    // TODO: Add optional webroot from siteInfo.
    $this['site.working_directory'] = function($c) {
      return $c['server']->getWebroot() . '/' . $c['name'];
    };

    // Some systems place the Drupal webroot in a subdirectory.
    // This option configures the name of the subdirectory (some use htdocs).
    $this['webroot_subdirectory'] = 'webroot';

    // The directory inside the working directory to place the drupal code.
    // Note the Drupal root may be in a subdirectory, see 'webroot_subdirectory'.
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
   * TODO: Deprecate this in favor of a constructor that receives an alias.
   */
  public function configureWithSiteInfo(Array $siteInfo) {

    if (isset($siteInfo['vcs'])) {
      $this['code_fetcher.class'] = $this['code_fetcher.vcs_mapping'][$siteInfo['vcs']];
    }

    // Merge in configuration.
    foreach ($siteInfo as $key => $value) {
      if (is_string($value)) {
        $this[$key] = trim($value);
      }
      else {
        $this[$key] = $value;
      }
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
      else if (is_string($value)) {
        $string .= "'" . str_replace("'", "\'", $value) . "'," . PHP_EOL;
      }
      else if (is_null($value)) {
        $string .= 'NULL,' . PHP_EOL;
      }
    }
    $string .= "$indent)";
    return $string;
  }


  /**
   * Sets the default for a key if it is not already set.
   *
   * @param $key
   *   A string representing the configuration key.
   * @param $value
   *   The default value to set if the key does not already have configuration.
   */
  public function setDefaultConfigration($key, $value) {
    if (!isset($this[$key])) {
      $this[$key] = $value;
    }
  }
}
