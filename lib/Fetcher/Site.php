<?php

namespace Fetcher;

use \Symfony\Component\Yaml\Yaml;
use \Pimple;
use Symfony\Component\Process\Process;

class Site extends Pimple implements SiteInterface {

  // A multi-dimensional array of build hooks.
  // TODO: buildhooks should be a class of task.
  protected $buildHooks = array();

  // An array of callable tasks keyed by name.
  protected $tasks = array();

  /**
   * Constructor function to populate the dependency injection container.
   */
  public function __construct($siteInfo = NULL) {
    // Populate defaults.
    $this->setDefaults();
    $this->registerDefaultTasks();
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
        $this['database']->createUser();
        $this['database']->grantAccessToUser();
      }
      else {
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

    // Ensure the server handler has been instantiated.
    $this['server'];
    // Ensure we have our files folders.
    $this['system']->ensureFolderExists($this['site.working_directory'] . '/public_files', NULL, $this['server.user']);
    $this['system']->ensureFolderExists($this['site.working_directory'] . '/private_files', NULL, $this['server.user']);
  }

  /**
   * Ensure the site folder exists.
   */
  public function ensureSiteFolder() {
    $this['system']->ensureFolderExists($this['site.directory'], NULL, $this['server.user']);
  }

  /**
   * Checks to see whether settings.php exists and creates it if it does not.
   */
  public function ensureSettingsFileExists() {
    // TODO: Support multisite?
    // TODO: This is ugly, what we're doing with this container here...
    $settingsFilePath = $this['site.directory'] . '/settings.php';
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
        'environment_local' => $conf['environment.local'],
      );
      // TODO: Get the settings.php for the appropriate version.
      $content = \drush_fetcher_get_asset('drupal.' . $this['version'] . '.settings.php', $vars);

      // If we have a site-settings.php file for this site, add it here.
      if (is_file($this['site.directory'] . '/site-settings.php')) {
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
    return $this['database_synchronizer']->syncDB();
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
        $c['site.working_directory'] . '/public_files' => $c['site.directory'] . '/files',
        $c['site.code_directory'] => $c['site.webroot'],
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
    // TODO: We have standardized on drush alias keys where possible, this is deprecated.
    $this['hostname'] = function($c) {
      return $c['uri'];
    };
    // The URI of the site.
    $this['uri'] = function($c) {
      return strtolower($c['name'] . '.' . $c['system hostname']);
    };

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

    // Some systems place the Drupal webroot in a subdirectory.
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

    $this['build_hook_file.path'] = function($c) {
      return $c['site.code_directory'] . '/sites/default/fetcher.make.php';
    };

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

  /**
   * Register a task that can be performed on the site.
   *
   * @param $name
   *   A variable safe machine name for the task.
   * @param $task
   *   Either a callable to perform the task or an array of task names for a task stack.
   *     The callable should take a Fetcher\Site object as its parameter.
   * @param $options
   *   An array of options which may contain the following keys:
   *    description: A description of the operation the task will perform. Without a description tasks are
   *      left out of the fetcher-task drush listing.
   *    starting_message: A message to display when the task is beginning.
   *      Useful for alerting users long running tasks are in progress.
   *    starting_message_arguments: An array of tokens to substitute in the starting message.
   *    starting_message_arguments_callback: A callable that receives the site object as the only paramter and
   *      returns the array generally specified in starting_message_arguments.
   *    success_message: A message to display if the task was succsesfull.
   *    success_message_arguments: An array of tokens to substitute in the success message.
   *    success_message_arguments_callback: A callable that receives the site object as the only paramter and
   *      returns the array generally specified in success_message_arguments.
   *    arguments: If this callable does not receive the site object as the sole parameter provide the arguments.
   */
  public function registerTask($name, $task, $options = array()) {
    if (!is_callable($task) && !is_array($task)) {
    }
    $this->tasks[$name] = $options;
    if (is_callable($task)) {
      $this->tasks[$name]['callable'] = $task;
    }
    else if (is_array($task)) {
      $this->tasks[$name]['stack'] = $task;
    }
    else {
      throw new \Exception('Invalid task definition. Tasks must be callables or an array of tasks.');
    }
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
      return FALSE;
    }
    return $this->tasks[$task];
  }

  /**
   * Accepts the bare data internal data structure as returned by getTask.
   *
   * This is provided primarily for messing with exisitng defined tasks (or task stacks).
   *
   * @param $name
   *   The name of the task.
   * @param $task
   *   An array representing the task.
   */
  public function setTask($name, $task) {
    $this->tasks[$name] = $task;
  }



   /**
    * Run a task by name.
    *
    * @param $name
    *   The name of the registered task (or task set) to run.
    */
  public function runTask($name) {
    $task = $this->getTask($name);
    if ($task === FALSE) {
      throw new \Exception(sprintf('Attempting to run undefined task %s.', $name));
    }
    if (isset($task['starting_message'])) {
      $arguments = !empty($task['starting_message_arguments']) ? $task['starting_message_arguments'] : array();
      if (!empty($task['starting_message_arguments_callback'])) {
        // By default tasks recieve the site object as the only parameter but an array of arguments can be specified.
        $arguments = call_user_func($task['starting_message_arguments_callback'], $this);
      }
      $this['log'](dt($task['starting_message'], $arguments), 'ok');
    }
    // If the task is an array run each task listed in its keys.
    if (!empty($task['stack'])) {
      foreach ($task['stack'] as $subtask) {
        $this->runTask($subtask);
      }
    }
    else {
      $arguments = !empty($task['arguments']) ? $task['arguments'] : array($this);
      call_user_func_array($task['callable'], $arguments);
    }
    if (isset($task['success_message'])) {
      $arguments = !empty($task['success_message_arguments']) ? $task['success_message_arguments'] : array();
      if (!empty($task['success_message_arguments_callback'])) {
        $arguments = call_user_func($task['success_message_arguments_callback'], $this);
      }
      $this['log'](dt($task['success_message'], $arguments), 'success');
    }
  }

  public function insertBeforeSubtask($task, $subtask, $taskToAdd) {
  }

  public function insertAfterSubtask() {
  }

  /**
   * Registers the default tasks that ship as methods on 
   */
  public function registerDefaultTasks() {

    $options = array(
      'description' => 'Ensure that a site is properly configured to run on this server.',
      'success_message' => 'Your site has been setup!',
    );
    $tasks = array(
      'before_build_hooks',
      'ensure_working_directory',
      'ensure_code',
      'ensure_database_connection',
      'ensure_settings_file',
      'ensure_symlinks',
      'ensure_drush_alias',
      'ensure_server_host_enabled',
      'load_make_file',
      'after_build_hooks',
    );
    $this->registerTask('ensure_site', $tasks, $options);

    $options = array(
      'description' => 'Completely remove this site and destroy all data associated with it on the server.',
      'success_message' => 'This site has been completely removed.',
    );
    $stack = array(
      'remove_site_site_method',
    );
    $this->registerTask('remove_site', $stack, $options);

    // Private method for calling remove on this site.
    // TODO: Break this into subtasks.
    $this->registerTask('remove_site_site_method', array($this, 'remove'));

    $options = array(
      'description' => 'Setup the working directory by creating folders, files, and symlinks.',
      'success_message' => 'The working directory is properly setup.',
    );
    $this->registerTask('ensure_working_directory', array($this, 'ensureWorkingDirectory'), $options);

    $options = array(
      'description' => 'Fetch the site\'s code from the appropriate place.',
      'starting_message' => 'Fetching code...',
      'success_message' => 'The code is in place.',
    );
    $this->registerTask('ensure_code', array($this, 'ensureCode'), $options);
    
    $options = array(
      'description' => 'Ensure the drupal database and database user exist creating the requisite grants if necessary.',
      'success_message' => 'The database exists and the site user has successfully conntected to it.',
    );
    $this->registerTask('ensure_database_connection', array($this, 'ensureDatabase'), $options);

    // Ensure the site's folder is in place.
    $this->registerTask('ensure_site_folder', array($this, 'ensureSiteFolder'));

    $options = array(
      'description' => 'Ensure the drupal database and database user exist creating the requisite databse, user, and grants if necessary.',
      'success_message' => 'The database exists and the site user has successfully conntected to it.',
    );
    $this->registerTask('ensure_database_connection', array($this, 'ensureDatabase'), $options);

    $options = array(
      'description' => 'Ensure the settings.php file is in place (and dynamically generate it if it is not).',
      'success_message' => 'The settings.php file is in place.',
    );
    $this->registerTask('ensure_settings_file', array($this, 'ensureSettingsFileExists'), $options);

    // Create necessary symlinks.

    $options = array(
      'description' => 'Ensure any configured symlinks have been created and point at the correct path.',
      'success_message' => 'All symlinks exist and point to the correct path.',
    );
    $this->registerTask('ensure_symlinks', array($this, 'ensureSymLinks'), $options);

    $options = array(
      'description' => 'Create a drush alias for this site.',
      'success_message' => 'The alias @!alias.local exists and resides in the file @path',
      'success_message_arguments_callback' => function($site) {
        return array(
          '!alias' => $this['name'],
          '@path' => $this->getDrushAliasPath(),
        );
      },
    );
    $this->registerTask('ensure_drush_alias', array($this, 'ensureDrushAlias'), $options);

    $options = array(
      'description' => 'Ensure that the configuration for this site has been captured in the site_info file for the site..',
      'success_message' => 'The site info file for this site has been created.',
    );
    $this->registerTask('ensure_drush_alias', array($this, 'ensureSiteInfoFileExists'), $options);

    $options = array(
      'description' => 'Ensure that the server is configured with the appropriate virtualhost or equivalent.',
      'success_message' => 'The site is enabled and is running at @hostname',
      'success_message_arguments_callback' => function($site) {
        return array('@hostname' => $site['hostname']);
      },
    );
    $this->registerTask('ensure_server_host_enabled', array($this, 'ensureSiteEnabled'), $options);


    $options = array(
      'description' => 'Synchronize the drupal database on this site with one on a remote server.',
      'start_message' => 'Attempting to sync database from remote...',
      'success_message' => 'The database was properly synchronized.',
    );
    $this->registerTask('sync_db', array($this, 'syncDatabase'), $options);

    $task = function ($site) {
      if (is_file($site['site.code_directory'] . '/sites/default/fetcher.make.php')) {
        require($site['site.code_directory'] . '/sites/default/fetcher.make.php');
      }
    };
    $this->registerTask('include_fetcher_make', $task);

    $options = array(
      'description' => 'Synchronize the drupal database on this site with one on a remote server.',
      'start_message' => 'Attempting to sync database from remote...',
      'success_message' => 'The database was properly synchronized.',
    );
    $this->registerTask('sync_db', array($this, 'syncDatabase'), $options);


    // If there is an fetcher.make.php file, load it to allow build hooks to be
    // registered.
    $makeLoader = function($site) {
      if (is_file($site['build_hook_file.path'])) {
        require($site['build_hook_file.path']);
      }
    };
    $this->registerTask('load_make_file', $makeLoader);

    $options = array(
      'start_message' => 'Running before build hooks...',
      'success_message' => 'Before build hooks completed.',
      'arguments' => array('before'),
    );
    $this->registerTask('before_build_hooks', array($this, 'runOperationBuildHooks'), $options);

    $options = array(
      'start_message' => 'Running after build hooks...',
      'success_message' => 'After build hooks completed.',
      'arguments' => array('after'),
    );
    $this->registerTask('after_build_hooks', array($this, 'runOperationBuildHooks'), $options);

  }
}
