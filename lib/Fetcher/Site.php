<?php

namespace Fetcher;

use \Symfony\Component\Yaml\Yaml;
use \Pimple;
use \Symfony\Component\Process\Process;
use \Fetcher\Utility\PHPGenerator,
    \Fetcher\Task\TaskStack,
    \Fetcher\Task\Task,
    \Fetcher\Utility\SettingsPHPGenerator,
    \Fetcher\Exception\FetcherException;

class Site extends Pimple implements SiteInterface {

  // A multi-dimensional array of build hooks.
  // TODO: buildhooks should be a class of task.
  protected $buildHooks = array();

  // An array of callable tasks keyed by name.
  public $tasks = array();

  /**
   * Constructor function to populate the dependency injection container.
   */
  public function __construct($conf = NULL) {
    // Pimple explodes if you use isset() and you have not yet set any value.
    $this['initialized'] = TRUE;
    $this['log'] = $this->protect(function($message) {
      print $message . PHP_EOL;
    });

    // Populate defaults.
    $this->setDefaults();
    // Default tasks currently must be registered before task stacks that use them
    // are defined.
    $this->registerDefaultTasks();
    // Configure with any settings passed into the constructor.
    if (!empty($conf)) {
      $this->configure($conf);
    }
    // Apply any configurators.
    $this->runConfigurators();
  }

  /**
   * Apply an array of configuration.
   *
   * This array is set on the object using array access.
   * See the Pimple docs for details.
   *
   * @param $conf
   *   An array of keys and values to be handed to the site object.
   * @param $overrideExising
   *   A flag to specify whether this configuration should be treated only
   *   as a set of defaults or whether it should override exising cofniguration.
   */
  public function configure(Array $conf, $overrideExisting = TRUE) {
    foreach ($conf as $key => $value) {
      if (!isset($this[$key]) || $overrideExisting) {
        if (is_string($value)) {
          // Conf files often end up with trailing whitespace, trim it.
          $this[$key] = trim($value);
        }
        else {
          $this[$key] = $value;
        }
      }
    }
    return $this;
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

  /**
   * Ensure the database exists, the user exists, and the user can connect.
   *
   * @fetcherTask ensure_database_connection
   * @description Ensure the drupal database and database user exist creating the requisite databse, user, and grants if necessary.
   * @afterMessage The database exists and the site user has successfully connected to it.
   * @stack ensure_site
   * @afterTask ensure_drush_alias
   */
  public function ensureDatabase() {
    if (!$this['database']->canConnect()) {
      if (!$this['database']->exists()) {
        $this['database']->createDatabase();
      }
      if (!$this['database']->userExists()) {
        $this['database']->createUser();
        $this['database']->grantAccessToUser();
      }
      else {
        $this['database']->grantAccessToUser();
      }
    }
  }

  /**
   * Get environment configuration.
   *
   * @param $environmentName
   *   The name of the environment to fetch configuration for.
   */
  public function getEnvironment($environmentName) {
    if (empty($this['environments'][$environmentName])) {
      throw new FetcherException(sprintf('Invalid environment %s requested.', $environmentName));
    }
    return $this['environments'][$environmentName];
  }

  /**
   * Configure the site object from one of the loaded environments.
   */
  public function configureFromEnvironment($environment = NULL) {
    if (empty($environment)) {
      $environment = $this['environment.remote'];
    }
    $environments = $this['environments'];
    if (!empty($environment) && !isset($environments[$environment])) {
      throw new FetcherException('Invalid environment specified.');
    }
    if (!empty($environments[$environment])) {
      foreach ($environments[$environment]  as $key => $value) {
        // Prevent environment specific settings from being persisted.
        $this->addEphemeralKey($key);
        $this[$key] = $value;
      }
    }
  }

  /**
   * Adds a key to the ephemeral list.
   */
  public function addEphemeralKey($key) {
    $ephemeralKeys = $this['configuration.ephemeral'];
    if (in_array($key, $ephemeralKeys) == FALSE) {
      $ephemeralKeys[] = $key;
      $this['configuration.ephemeral'] = $ephemeralKeys;
    }
  }

  /**
   * Build the drush alias and place it in the home folder.
   *
   * @fetcherTask ensure_drush_alias
   * @description Create a drush alias for this site.
   * @afterMessage The alias [[name]].local exists and resides in the file [[drush_alias.path]].
   * @stack ensure_site
   * @afterTask ensure_sym_links
   */
  public function ensureDrushAlias() {
    // TODO: More of this should probably move into another class.
    $drushPath = $this['system']->getUserHomeFolder() . '/.drush';
    $this['system']->ensureFolderExists($drushPath);
    $drushFilePath = $this['drush_alias.path'];
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
        'root' => $this['site.webroot'],
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
        $content .= "\$aliases['$name'] = " . PHPGenerator::arrayExport($environment, $string, 0) . ";" . PHP_EOL;
      }
      $this['system']->writeFile($drushFilePath, $content);
    }
  }

  /**
   * Setup our basic working directory.
   *
   * @fetcherTask ensure_working_directory
   * @description Setup the working directory by creating folders, files, and symlinks.
   * @afterMessage The working directory is properly setup.
   * @stack ensure_site
   */
  public function ensureWorkingDirectory() {
    // TODO: Make this more dynamic, we should be able to support things like
    // the lullabot boilerplate layout.

    // Ensure we have our working directory.
    $this['system']->ensureFolderExists($this['site.working_directory']);

    // TODO: Move more of the log stuff into configuration.
    // Ensure we have a log directory.
    $this['system']->ensureFolderExists($this['site.working_directory'] . '/logs');

    // Ensure we have our log files.
    // TODO: We probably only want these on dev.
    $this['system']->ensureFileExists($this['site.working_directory'] . '/logs/access.log');
    $this['system']->ensureFileExists($this['site.working_directory'] . '/logs/mail.log');
    $this['system']->ensureFileExists($this['site.working_directory'] . '/logs/watchdog.log');

    // Ensure the server handler has been instantiated.
    // We do this because the server creates the server.user config key.
    $this['server'];
    // Ensure we have our files folders.
    $this['system']->ensureFolderExists($this['site.working_directory'] . '/public_files', NULL, $this['server.user']);
    $this['system']->ensureFolderExists($this['site.working_directory'] . '/private_files', NULL, $this['server.user']);
  }

  /**
   * Ensure the site folder exists.
   *
   * @fetcherTask ensure_site_folder
   */
  public function ensureSiteFolder() {
    $this['system']->ensureFolderExists($this['site.directory'], NULL, $this['server.user']);
  }

  /**
   * Checks to see whether settings.php exists and creates it if it does not.
   *
   * @fetcherTask ensure_settings_file
   * @description Ensure the settings.php file is in place (and dynamically generate it if it is not).
   * @afterMessage The settings.php file is in place.
   * @stack ensure_site
   * @afterTask ensure_code
   */
  public function ensureSettingsFileExists() {
    $settingsFilePath = $this['site.directory'] . '/settings.php';
    // Ensure the site folder exists.
    $this['system']->ensureFolderExists($this['site.directory']);
    // If the settings file does not exist, create a new one.
    if (!is_file($settingsFilePath)) {

      // We need to ensure any plugin that wants to add configuration has a chance to do so.
      // Running this evaluates all plugin constructors.
      $this->exportConfiguration();

      $compiler = new SettingsPHPGenerator();
      $compiler->set('iniSettings', $this['settings_php.ini_set']);
      $compiler->set('variables', $this['settings_php.variables']);
      $compiler->set('requires', $this['settings_php.requires']);    

      // If we have a site-settings.php file for this site, add it.
      if (is_file($this['site.directory'] . '/site-settings.php')) {
        $requires = $compiler->get('requires');
        $requires[] = $this['site.directory'] . '/site-settings.php';
        $compiler->set('requires', $requires);
      }

      $this['system']->ensureFolderExists($this['site.directory']);
      $this['system']->writeFile($settingsFilePath, $compiler->compile());
    }
  }


  /**
   * Ensure the code is in place.
   *
   * @fetcherTask ensure_code
   * @description Fetch the site's code from the appropriate place.
   * @beforeMessage Fetching code...
   * @afterMessage The code is in place.
   * @stack ensure_site
   * @afterTask ensure_working_directory
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
   * Ensure that all configured symlinks have been created.
   *
   * Note, with standard layout the webroot symlink is created separately.
   *
   * @fetcherTask ensure_sym_links
   * @description Ensure any configured symlinks have been created and point at the correct path.
   * @afterMessage All symlinks exist and point to the correct path.
   * @stack ensure_site
   * @afterTask ensure_settings_file
   */
  public function ensureSymLinks() {
    foreach ($this['symlinks'] as $realPath => $symLink) {
      $this['system']->ensureSymLink($realPath, $symLink);
    }
  }

  /**
   * Ensure the site has been added to the appropriate server.
   *
   * On apache this involves creating a vhost entry.
   *
   * @fetcherTask ensure_server_host_enabled
   * @description Ensure that the server is configured with the appropriate virtualhost or equivalent.
   * @afterMessage The site is enabled and is running at [[hostname]].
   * @stack ensure_site
   * @afterTask ensure_site_info_file
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
   *
   * @fetcherTask sync_db
   * @description Synchronize the drupal database on this site with one on a remote server.
   * @beforeMessage Attempting to sync database from remote...
   * @afterMessage The database was properly synchronized.
   */
  public function syncDatabase() {
    return $this['database_synchronizer']->syncDB();
  }

  /**
   * Synchronize the files with a remote environment.
   *
   * @fetcherTask sync_files
   * @description Sync files from a remote environment.
   * @afterMessage Files synced successfully.
   */
  public function syncFiles($type = 'both') {
    return $this['file synchronizer']->syncFiles($type);
  }

  /**
   * Removes The working diretory from this system.
   *
   * @fetcherTask remove_working_directory
   * @description Remove the working directory.
   * @afterMessage Removed `[[site.working_directory]]`.
   * @stack remove_site
   */
  public function removeWorkingDirectory($site = NULL) {
    if (is_null($site)) {
      $site = $this->site;
    }
    $site['system']->ensureDeleted($site['site.working_directory']);
  }

  /**
   * Removes drush aliases for this site from this system.
   *
   * @fetcherTask remove_drush_aliases
   * @description Remove the site's drush aliases.
   * @afterMessage Removed `[[drush_alias.path]]`.
   * @stack remove_site
   */
  public function removeDrushAliases($site = NULL) {
    if (is_null($site)) {
      $site = $this->site;
    }
    $site['system']->ensureDeleted($site['drush_alias.path']);
  }

  /**
   * Removes the site's database and user.
   *
   * @fetcherTask remove_database
   * @description Remove the site's database and user.
   * @afterMessage Removed database `[[database.database]]` and user `[[database.user.database]]@[[database.user.hostname]]`.
   * @stack remove_site
   */
  public function removeDatabase($site = NULL) {
    if (is_null($site)) {
      $site = $this->site;
    }
    if ($site['database']->exists()) {
      $site['database']->removeDatabase();
    }
    if ($site['database']->userExists()) {
      $site['database']->removeUser();
    }
  }

  /**
   * Removes the site's virtualhost.
   *
   * @fetcherTask remove_vhost
   * @description Remove the site's virtualhost (or server equivalent).
   * @afterMessage Removed virtual host for `[[hostname]]`.
   * @stack remove_site
   */
  public function removeVirtualHost($site = NULL) {
    if (is_null($site)) {
      $site = $this->site;
    }
    $site['server']->ensureSiteRemoved();
  }

  /**
   * Register a build hook that can be run before or after a site build.
   *
   * @param $operation
   *   The operation upon which this hook will fire.
   *    'initial' - TODO: Document this.
   *    'before' - TODO: Document this.
   *    'after' - TODO: Document this.
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
   * Get the list of build hooks for this operation.
   */
  public function getOperationBuildHooks($operation) {
    if (!empty($this->buildHooks[$operation])) {
      return $this->buildHooks[$operation];
    }
  }

  /**
   * Run all registered callbacks for an operation.
   *
   * TODO: Reimplement these as tasks, this function is bazonkers and gross.
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
          $process->setTimeout(NULL);
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
   * Export the configuration of the object to an array.
   */
  public function exportConfiguration() {
    $keys = $this->keys();
    // We do this the first time to instantiate our handler classes allowing
    // them to set their own defaults.
    foreach ($keys as $key) {
      if (!in_array($key, $this['configuration.ephemeral'])) {
        $this[$key];
      }
    }
    $keys = $this->keys();
    sort($keys);
    foreach ($keys as $key) {
      // Ensure we should be storing this configuration.
      if (!in_array($key, $this['configuration.ephemeral'])) {
        $value = $this[$key];
        // Ensure this is the sort of value we can store.
        if (!is_object($value) || get_class($value) == 'stdClass') {
          $conf[$key] = $value;
        }
      }
    }
    return $conf;
  }

  /**
   * Write a site info file from our siteInfo if it doesn't already exist.
   *
   * @fetcherTask ensure_site_info_file
   * @description Ensure that the configuration for this site has been captured in the site_info file for the site.
   * @afterMessage The site info file for this site has been created.
   * @stack ensure_site
   * @afterTask ensure_database_connection
   */
  public function ensureSiteInfoFileExists() {
    $conf = array();
    $string = Yaml::dump($this->exportConfiguration(), 5, 2);
    $this['system']->writeFile($this['site.info path'], $string);
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
    if (is_file($this['site.info path'])) {
      $yaml = file_get_contents($this['site.info path']);
      $info = $this->parseSiteInfo($yaml);
      return $info;
    }
  }

  /**
   * Configure the site object from the siteInfo file.
   */
  public function configureWithSiteInfoFile() {
    if ($conf = $this->getSiteInfoFromInfoFile()) {
      $this->configure($conf);
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Apply any configured configurators.
   */
  public function runConfigurators() {
    // Set default configuration from configrator list.
    if (isset($this['configurators'])) {
      foreach ($this['configurators'] as $configurator) {
        $configurator::configure($this);
      }
    }
  }

  /**
   * TODO: fetchInfo() should be a method on the site object that can load
   * from a file or load from site_info.yaml.
   *
   * @param $force_remote
   *   Whether to ignore the potential location of a site_info.yaml file and
   *   load directly from the configured InfoFetcher.class instead.
   * @return
   *   TRUE on success NULL if information could not be loaded.
   */
  public function fetchInfo($force_remote = FALSE) {
    if (!isset($this['name'])) {
      return FALSE;
    }
    // We don't go looking for an info file if `name` isn't set.
    if (!$force_remote && is_file($this['site.info path'])) {
      return $this->configureWithSiteInfoFile();
    }
    else {
      if ($conf = $this['info_fetcher']->getInfo($this['name.global'])) {
        $this->configure($conf);
        return TRUE;
      }
    }
  }

  /**
   * Populate this object with defaults.
   */
  public function setDefaults() {

    // Defaults to the local name.
    $this['name'] = function($c) {
      return $c['name.global'];
    };

    // Symlinks that need to be created.
    $this['symlinks'] = function ($c) {
      return array(
        $c['site.working_directory'] . '/public_files' => $c['site.directory'] . '/files',
        $c['site.code_directory'] => $c['site.webroot'],
      );
    };

    $this['process'] = $this->protect(function() {
      // This is the only way to dynamically instantiate an object with unknown
      // dynamic parameters.
      $reflection = new \ReflectionClass('Symfony\Component\Process\Process');
      $process = $reflection->newInstanceArgs(func_get_args());
      $process->setTimeout(NULL);
      return $process;
    });

    // If the log.function is changed it must have the same function signature.
    $this['log.function'] = $this->protect(function($message) {
      print $message . PHP_EOL;
    });;

    // We need a copy of site to close over in our closure.
    $site = $this;
    $this['log'] = $this->protect(function() use ($site) {
      $args = func_get_args();
      return call_user_func_array($site['log.function'], $args);
    });
    unset($site);

    // TODO: Do some detection?
    $this['system class'] = '\Fetcher\System\Posix';

    // Load a plugin appropriate to the system.
    $this['system'] = $this->share(function($c) {
      return new $c['system class']($c);
    });

    // Set our default server to Apache2.
    $this['server class'] = '\Fetcher\Server\Apache2';

    // Load a plugin appropriate to the server.
    $this['server'] = $this->share(function($c) {
      return new $c['server class']($c);
    });

    // Set our default database to MySQL.
    $this['database class'] = '\Fetcher\DB\Mysql';

    // Load a plugin appropriate to the database.
    $this['database'] = $this->share(function($c) {
      return new $c['database class']($c);
    });

    $this['database.driver'] = $this->share(function($c) {
      return $c['database class']::getDriver();
    });

    // Map the version control system specified to the handler.
    $this['code_fetcher.vcs_mapping'] = array(
      'git' => 'Fetcher\CodeFetcher\VCS\Git',
    );

    // Default to the most recent stable release.
    $this['version'] = 7;

    // Set our default code fetcher class to drush download.
    $this['code_fetcher.class'] = function($c) {
      if (isset($c['vcs'])) {
        return $c['code_fetcher.vcs_mapping'][$c['vcs']];
      }
      else {
        return 'Fetcher\CodeFetcher\Download';
      }
    };

    $this['code_fetcher.config'] = array();

    // Load a plugin appropriate to the Code Fetcher.
    $this['code_fetcher'] = $this->share(function($c) {
      $class = $c['code_fetcher.class'];
      return new $class($c);
    });

    // For most cases, the Drush sql-sync command can be used for synchronizing.
    $this['database_synchronizer.class'] = 'Fetcher\DBSynchronizer\DrushSqlSync';

    $this['database_synchronizer'] = $this->share(function($c) {
      return new $c['database_synchronizer.class']($c);
    });

    // For most cases, RSync is file for file synchronizing. We'll find the
    // path to the files via drush.
    $this['file synchronizer class'] = 'Fetcher\FileSynchronizer\RsyncFileSync';

    $this['file synchronizer'] = $this->share(function($c) {
      return new $c['file synchronizer class']($c);
    });

    // Usually set by the drush option.
    // If set print log messages but take no actions.
    $this['simulate'] = FALSE;

    // Usually set by drush option.
    // Prints more verbose logs.
    $this['verbose'] = FALSE;

    // The hostname of the system.
    $this['system hostname'] = function ($c) {
      return $c['system']->getHostname();
    };

    // By defualt the profile for newly created sites is just Drupal core.
    $this['profile'] = 'standard';

    $this['profile.package'] = function($c) {
      if (in_array($c['profile'], array('standard', 'minimal'))) {
        return 'drupal-' . $c['version'];
      }
      else {
        return $c['profile'];
      }
    };

    // The URI of the site.
    // TODO: We have standardized on drush alias keys where possible, this is deprecated.
    $this['hostname'] = function($c) {
      return strtolower($c['name'] . '.' . $c['system hostname']);
    };

    $this['settings_php.ini_set'] = array();
    $this['settings_php.variables'] = function($c) {
      return array(
        'conf' => array(
          'fetcher_environment' => $c['environment.local'],
        ),
      );
    };
    $this['settings_php.requires'] = array();

    // TODO: This is not the best way to do this:
    // TODO: Add optional webroot from siteInfo.
    $this['site.working_directory'] = function($c) {
      // Ensure the server class has been instantiated.
      $c['server'];
      return $c['server.webroot'] . '/' . $c['name'];
    };

    $this['site'] = 'default';

    $this['site.webroot'] = function($c) {
      return $c['site.working_directory'] . '/webroot';
    };

    $this['site.directory'] = function($c) {
      return $c['site.code_directory'] . '/sites/' . $c['site'];
    };

    // Some systems (including Acquia) place the Drupal webroot in a subdirectory.
    // This option configures the name of the subdirectory (some use htdocs).
    $this['webroot_subdirectory'] = 'webroot';

    // The directory inside the working directory to place the drupal code.
    // Note the Drupal root may be in a subdirectory, see 'webroot_subdirectory'.
    $this['site.code_directory'] = function($c) {
      return $c['site.working_directory'] . '/' . 'code';
    };

    // Set the path where the site info yaml file should be placed.
    $this['site.info path'] = function($c) {
      return $c['site.working_directory'] . '/site_info.yaml';
    };

    // Register our service for generating a random string.
    $this['random'] = $this->protect(
      function($length = 20) {
        return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
      }
    );

    $this['environment.local'] = 'local';
    $this['environments'] = array();

    $this['build_hook_file.path'] = function($c) {
      return $c['site.code_directory'] . '/sites/' . $c['site'] . '/fetcher.make.php';
    };

    $this['drush_alias.path'] = function($c) {
      return $c['system']->getUserHomeFolder() . '/.drush/' . $c['name'] . '.aliases.drushrc.php';
    };

    // These keys are not persisted to the site_info.yaml file.
    $this['configuration.ephemeral'] = array(
      'initialized',
      'simulate',
      'verbose',
      'environment.remote',
    );

    $this['info_fetcher.class'] = 'Fetcher\InfoFetcher\DrushAlias';
    // Load a plugin appropriate to the info fetcher.
    $this['info_fetcher'] = $this->share(function($c) {
      return new $c['info_fetcher.class']($c);
    });

    $this['task_loader.class'] = '\Fetcher\Task\TaskLoader';
    // Load a plugin appropriate to the Task Loader.
    $self = $this;
    $this['task_loader'] = $this->share(function($c) use ($self) {
      $class = $c['task_loader.class'];
      $loader = new $class($c);
      $loader->setTasks($self->tasks);
      return $loader;
    });
  }

  /**
   * Returns the internal datastrcuture representing all registered tasks.
   *
   * TODO: Should we be a bit smarter and less naked?
   */
  public function getTasks() {
    return $this->tasks;
  }

  /**
   * Returns the internal datastructure for a single task by name.
   */
  public function getTask($task) {
    if (empty($this->tasks[$task])) {
      return NULL;
    }
    return $this->tasks[$task];
  }

  /**
   * Accepts the bare internal data structure as returned by Site::getTask().
   *
   * This is provided primarily for messing with exisitng defined tasks (or task
   * stacks).
   *
   * @param $name
   *   The name of the task.
   * @param $task
   *   An array representing the task.
   */
  public function addTask($task) {
    $this->tasks[$task->fetcherTask] = $task;
  }

  /**
   * Run a task by name.
   *
   * @param $name
   *   The name of the registered task (or task set) to run.
   */
  public function runTask($name) {
    $task = $this->getTask($name);
    if ($task === NULL) {
      throw new FetcherException(sprintf('Attempting to run undefined task %s.', $name));
    }
    $task->run($this);
  }

  /**
   * Add a task to an existing task stack.
   *
   * @param $taskStack
   *   The name of a configured TaskStack.
   * @param $task
   *   The task to add, can be a task or an object implementing
   *   \Fetcher\Task\TaskInterface().
   */
  public function addSubTask($taskStack, $task) {
    $stack = $this->getTask($taskStack);
    if (empty($stack)) {
      throw new FetcherException('Can not add a task to an undefined task stack.');
    }
    if (is_string($task)) {
      $task = $this->getTask($task);
    }
    $stack->addTask($task);
  }

  /**
   * Registers the default tasks that ship as methods on the Site class.
   *
   * TODO: Should this be named more intuitively?
   */
  public function registerDefaultTasks() {
    // In the task loader we pass a reference to the tasks
    // on this object.
    $this['task_loader']->scanObject($this);
  }

}
