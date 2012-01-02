<?php

/**
 * Implements hook_drush_help().
 */
function ignition_drush_help($section) {
  switch ($section) {
    case 'meta:ignition:title':
      return dt('Ignition commands');
    case 'meta:ignition:summary':
      return dt('Interact locally with sites managed by Ignition.');
  }
}

/**
 * Implements hook_drush_command().
 */
function ignition_drush_command() {

  $items = array();

  // Options necessary for all commands connecting to the Ignitions server.
  $shared = array(
    'ignition-server' => 'The URL of the ignition server.',
    'ignition-key' => 'The URL of the ignition server.',
  );

  // Options necessary for interacting with sites on this server.
  $environment_options = array(
    'ignition-system' => 'The type of system used (currently only supports Ubuntu).',
    'ignition-server' => 'The type of server to use (currently only supports Apache2).',
    'ignition-database' => 'The type of database to use (currently only supports MySQL).',
    'ignition-sql-user' => 'The database username to use for administration commands.',
    'ignition-sql-password' => 'The database password to use for administration commands.',
  );

  $items['ignition-list'] = array(
    'description' => 'List all sites available to Ignition that this user has access to.',
    'aliases' => array('il'),
    'arguments' => array(
      'search' => 'Any part of the name of a site to search for.',
      'page' => 'The page of listings starting from zero.  Necessary only if there are too many results to display on a page of 100.',
    ),
  );

  $items['ignition-info'] = array(
    'description' => 'List known information about a site managed by Ignition.',
    'aliases' => array('if'),
    'arguments' => array(
      'search' => 'The name of a site to list information for.',
    ),
  );

  $environment_items['ignition-get'] = array(
    'description' => 'Setup a site on this machine managed by Ignition synchronizing the database and files if desired.',
    'aliases' => array('ig'),
    'arguments' => array(
      'site' => 'The name of a site to search install.',
      'environment' => 'The name of the environment to load from.  Optional but required with database or file syncing.',
    ),
    'options' => array(
      'branch' => 'The branch of the repository to checkout',
      'tag' => 'The tag of the repository to checkout',
      'hostname' => 'The hostname to use in the server config (defaults to [site_name].[hostname]).',
      // TODO: Implement this:
      //'environment' => 'The environment to use for database and file syncing (defaults to dev if it exists).',
      // TODO: Implement this:
      //'deploy-name' => 'The name of the site to use for this deployment. This option allows you to get more than one variation of a site on one system.',
    ),
  );

  $environment_items['ignition-create'] =  array(
    'description' => 'Setup a site on this machine similar to an Ignition site.  Note this will not add the site to the Ignition server.',
    'aliases' => array('ic'),
    'arguments' => array(
      'site_name' => 'The name of a site to create.',
      'version' => 'The version of Drupal to install (the same options that would be passed to pm-download without the `drupal-` prefix).',
    ),
    'options' => array(
      'package-handler' => 'The package handler to use for downloading drupal, defaults to drupalorg_git but any can be used that work with pm-download.',
    ),
  );


  $environment_items['ignition-release'] = array(
    'description' => 'Release an Ignition managed site to a specific tag.',
    'aliases' => array('ir'),
    'arguments' => array(
      'site name' => 'The name of the site to release.',
      'tag' => 'The tag to release. Branches are not supported.',
    ),
    'options' => array(
      'backup' => 'Flag to determine whether to create a backup.',
      'backup-dir' => 'Path to create a SQL dump.',
    ),
  );

  $environment_items['ignition-delete'] = array(
    'description' => 'Remove an Ignition managed site from this server entirely.',
    'aliases' => array('id'),
    'arguments' => array(
      'site' => 'The name of the site to delete.',
    ),
    'options' => array(
    ),
  );

  // Add environment specific options relevant to the server
  // on which ignition is being run for the appropriate commands.
  foreach ($environment_items as $name => $item) {
    $items[$name] = $item;
    $item[$name]['options'] = array_merge($environment_options, $item['options']);
  }

  // Add the base options for all Ignition commands.
  foreach ($items as &$item) {
    $item['bootstrap'] = DRUSH_BOOTSTRAP_DRUSH;
    if (isset($item['options'])) {
      $item['options'] = array_merge($shared, $item['options']);
    }
    else {
      $item['options'] = $shared;
    }
  }
  return $items;
}

/**
 * Autoloader for Ignition.
 *
 * @param $class
 *   The name of the class to load.
 * @return
 *   True if the class was successfully loaded.
 */
function ignition_autoload($class) {
  static $search_paths;

  // Only take action if searching for an Ignition class.
  if (strpos($class, 'Ignition\\') === 0) {
    if ($search_paths === NULL) {
      // TODO: use a proper method for discovering the base path to the cmd file
      $command = drush_get_command();
      $search_paths = array(
        // Classes in base Ignition install.
        $command['path'] . '/lib/',
      );
    }

    // Try the search paths in order.
    foreach ($search_paths as $base_path) {
      $file = $base_path . strtr($class, '\\', '/') . '.php';
      if (is_file($file)) {
        require $file;
        return TRUE;
      }
    }
  }
}

/**
 * Implements hook_drush_init().
 *
 * Register our autoloader.
 */
function ignition_drush_init() {
  spl_autoload_register('ignition_autoload');
}

/**
 * List sites in the ignition install.
 */
function drush_ignition_list($name = '') {
  $client = drush_ignition_get_request_handler();
  $client->setPath('ignition/api/site.json');
  if ($name != '') {
    $client->addParam('name', $name);
  }
  if ($page != '') {
    $client->addParam('page', $page);
  }
  $result = $client->fetch();
  // TODO: We should have one common error status to check.
  if ($result !== FALSE && $result !== NULL) {
    $rows[] = array(
      'Name',
      'Title',
      //t('client'),
    );
    foreach($result as $name => $info) {
      $rows[] = array($name, $info->name);
    }
    drush_print_table($rows, TRUE);
  }
  else {
    drush_log('The data could not be retrieved from the server.', 'error');
  }
}

/**
 * Print out information about a single site.
 */
function drush_ignition_info($site_name) {

  drush_ignition_load_private_key_from_ssh_agent();

  $site_info = drush_ignition_get_site_info($site_name);
  if ($site_info) {
    drush_print_r($site_info);
  }
}

function drush_ignition_create($site_name, $version) {

  // TODO: Most of this should probably be moved into the site class iteslf.

  // Build a new site object.
  $site_info = new stdClass;
  $site_info->name = $site_name;
  $site_info->version = $version;
  $site = drush_ignition_get_site($site_info);

  // Make sure that the project direcotry is properly configured on the server.
  $success = $site->setUpWorkingDirectory();

  // Checkout the site in the appropriate location.
  //$success = $site->checkout(drush_get_option('branch', NULL));

  $commandline_args = array(
    'drupal-' . $version,
  );
  $commandline_options = array(
    // Default our package hander to git_drupalorg.
    'package-handler' => drush_get_option('package-handler', 'git_drupalorg'),
    'drupal-project-rename' => 'code',
    'destination' => $site->getWorkingDirectory(),
  );
  foreach ($commandline_options as $name => $value) {
    drush_set_option($name, $value);
  }
  // Note we don't use drush_invoke_process() because it requires an existinge
  // site.
  drush_invoke('pm-download', $commandline_args);
  return;

  // Create symlinks and ensure we have a settings.php.
  $success = $site->ensureSymLinks();



  // Install the site
  $commandline_args = array();
  $commandline_options = array(
    'root' => $site->getCodePath(),
    'db-url' => '',
    'account-name' => 'admin',
    'account-pass' => 'pass',
    'site-name' => $ite_name,
  );
  drush_invoke_process('@self', 'site-install', $commandline_args, $commandline_options, TRUE);
}

/**
 * Do all of the
 */
function drush_ignition_get($site_name) {

  // TODO: Document installation instructions.
  $site_info = drush_ignition_get_site_info($site_name);
  if ($site_info === NULL) {
    // TODO: Check for connectivity problems.
    //if ($client->meta
    drush_log('This site does not appear to exist.', 'error');
    return;
  }

  // Instantiate the site object.
  $site = drush_ignition_get_site($site_info);

  // Make sure that the project direcotry is properly configured on the server.
  $success = $site->setUpWorkingDirectory();

  // Checkout the site in the appropriate location.
  // TODO: Our options support branches and tags, don't hard code branch here.
  $success = $site->checkout(drush_get_option('branch', NULL));

  // Create symlinks and ensure we have a settings.php.
  $success = $site->ensureSymLinks();
  //$success = $site->ensureSettingsFileExists();


  // Ensure we have a database and user.
  $success = $site->ensureDatabase();


  // TODO: Actually set this to a failure if any of the items above fail.
  if ($success) {
    drush_log('Your site has been setup!', 'success');
  }
  else {
    drush_log('Something went wrong along the way, try running with `--verbose` to find the problem.', 'error');
  }
}

function drush_ignition_get_site_info($site_name) {
  $client = drush_ignition_get_request_handler();
  return $client
    ->setPath("ignition/api/site/$site_name.json")
    // Most of our requests retrieve information, so default to a GET request.
    ->setMethod('GET')
    // Set a 3 second timeout.
    ->setTimeout(3)
    ->fetch();
}


function drush_ignition_delete($site_name) {
  // TODO: Validate $site_name.
  // TODO: Allow deletion of other webroots (check site info?).
  if (drush_confirm("Are you sure you want to delete $site_name?")) {
    $site_info = new stdClass;
    $site_info->name = $site_name;
    $result = drush_ignition_get_site($site_info)->delete();
  }
}

/**
 * Factory function for instatiating a site object.
 */
function drush_ignition_get_site($site_info = NULL) {
  // Attempt to load a plugin appropriate to the system.
  $config['system'] = drush_ignition_get_handler('System', drush_get_option('ignition-system'));
  // Attempt to load a plugin appropriate to the server.
  $config['server'] = drush_ignition_get_handler('Server', drush_get_option('ignition-server', 'Apache2'));
  // Load a database plugin.
  $config['database'] = drush_ignition_get_handler('DB', drush_get_option('ignition-database'));
  // Load the appropriate VCS plugin.
  if (!is_null($site_info) && isset($site_info->vcs)) {
    $config['vcs'] = drush_ignition_get_handler('VCS', $site_info->vcs);
  }
  else {
    $config['vcs'] = drush_ignition_get_handler('VCS', 'Git');
  }
  // TODO: This still doesn't feel right.  We shouldn't load the db data here.
  $config['dbSpec'] = array(
    'database' => drush_get_option('database', $site_info->name),
    'port' => drush_get_option('database-port', 3306),
    'username' => drush_get_option('database-user', $site_info->name),
    'password' => drush_get_option('database-password', drush_ignition_make_random_password()),
    'host' => 'localhost',
    'driver' => $config['database']->getDriver(),
  );
  // Add the loaded site information.
  $config['siteInfo'] = $site_info;
  return new Ignition\Site($config);
}

function drush_ignition_release() {
  // Optionally create a MySQL backup.
  // Checkout a new tag.
  // Create settings.php and files symlinks.
  // Put the site in offline mode.
  // Update the webroot symlink.
  // Run update.php
  // Take the site out of offline mode.
}

// TODO: Add this to the hook_command implementation above.
function drush_ignition_subcommand() {
  // Just planning the available subcommands.
  $subcommands = array(
    'create-working-directory' => '',
    'get-code' => '',
    'create-database' => '',
    'sync-database' => '',
    'sync-files' => '',
  );
}

/**
 * Create an HTTP Request.
 */
function drush_ignition_get_request_handler() {
  $client = new Ignition\Utility\RESTClient();
  if (!drush_get_option('ignition-server', FALSE) || !drush_get_option('ignition-key', FALSE)) {
    drush_log('The ignition server and ignition key options must be set, recommend setting them in your .drushrc.php file.', 'error');
    return FALSE;
  }
  $client->setURL(drush_get_option('ignition-host'))
    // TODO: Add authentication.
    //->addParam('ignition-key', drush_get_option('ignition-key'))
    ->setMethod('GET')
    ->setTimeout(3)
    ->setFormat('json');
  return $client;
}

/**
 * Essentially stolen from Drupal 7's `drupal_random_bytes`.
 */
function drush_ignition_make_random_password() {
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

function drush_ignition_get_handler($type, $handler) {
  // TODO: Determine whether we could have a much better plugin system (hint: we can).
  $handler = strtolower($handler);
  $handler[0] = strtoupper($handler[0]);
  $class = 'Ignition\\' . $type . '\\' . $handler;
  return new $class;
}

/**
 *
 */
function drush_ignition_write_file($path, $contents) {
  drush_log("Writing file to $path");
  //file_put_contents($path, $contents);
}
