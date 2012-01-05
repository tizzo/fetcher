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
 * List sites in the ignition install.
 */
function drush_ignition_list($name = '', $page = '') {

  $container = drush_ignition_get_service_container();
  $client = $container['ignition client'];
  $client->setPath('ignition/api/site.json');

  // If we have a name to search for add it to the query.
  if ($name != '') {
    $client->addParam('name', $name);
  }
  // If we are paging past the first 100 results, add the page.
  if ($page != '') {
    $client->addParam('page', $page);
  }

  // Execute the request and decode the response.
  $result = $client->fetch();

  // TODO: We should have one common error status to check.
  if ($result !== FALSE) {
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
function drush_ignition_info($site_name = NULL) {
  if (is_null($site_name) && !$site_name != drush_ignition_prompt_for_site_name()) {
    return;
  }
  $site_info = drush_ignition_get_site_info($site_name);
  if ($site_info) {
    drush_print_r($site_info);
  }
}

/**
 *
 */
function drush_ignition_create($site_name = FALSE, $version = '7') {

  if (!$site_name && !$site_name = drush_ignition_prompt_for_site_name()) {
    return;
  }

  // TODO: Most of this should probably be moved into the site class iteslf.

  // Build a new site object.
  $site_info = new stdClass;
  $site_info->name = $site_name;
  $site_info->version = $version;
  $site = drush_ignition_get_site($site_info);

  try {
    // Make sure that the project direcotry is properly configured on the server.
    $success = $site->setUpWorkingDirectory();

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
  catch (Exception $e) {
    drush_log($e->getMessage(), 'error');
    drush_log(dt('Something failed in site creation, consider running `drush ignition-delete @site_name.', array('@site_name', $site_name)), 'error');
  }
}

/**
 * Setup a local copy of an Ignition site.
 *
 * Do all of the work to setup a local copy of a site tracked
 * by Ignition, optionally syncronizing database and file with
 * an environment tracked by Ignition.
 */
function drush_ignition_get($site_name) {

  // TODO: Document installation instructions.
  $site_info = drush_ignition_get_site_info($site_name);
  if ($site_info === FALSE) {
    drush_log(dt('This site information could not be loaded.'), 'error');
    return;
  }

  try {
    // Instantiate the site object.
    $site = drush_ignition_get_site($site_info);

    // Make sure that the project direcotry is properly configured on the server.
    $site->setUpWorkingDirectory();

    // Checkout the site in the appropriate location.
    // TODO: Our options support branches and tags, don't hard code branch here.
    $site->checkout(drush_get_option('branch', NULL));

    // Create symlinks and ensure we have a settings.php.
    $site->ensureSymLinks();
    //$success = $site->ensureSettingsFileExists();


    // Ensure we have a database and user.
    $site->ensureDatabase();

    drush_log('Your site has been setup!', 'success');
  }
  catch (Exception $e) {
    drush_log('Something went wrong along the way, try running with `--verbose` to find the problem.', 'error');
  }
}

/**
 * Issue a request against 
 */
function drush_ignition_get_site_info($site_name) {
  $container = drush_ignition_get_service_container();
  $client = $container['ignition client'];
  $result = $client
    ->setPath("ignition/api/site/$site_name.json")
    ->fetch();
  if ($result === FALSE) {
    $code = $client->getResponseCode();
    if ($code == 401) {
      drush_log('Access was denied, please check your authentication credentials.', 'error');
    }
    else if ($code == 404) {
      drush_log('The requested site could not be found on the server.', 'error');
    }
    else {
      drush_log('The data could not be retrieved from the server.', 'error');
    }
    return FALSE;
  }
  return $result;
}


/**
 * Delete an ignition site.
 */
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

  $container = drush_ignition_get_service_container();
  $container->configureWithSiteInfo($site_info);

  return $container['site'];
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

/**
 *
 */
function drush_ignition_prompt_for_site_name() {
  $site_name = drush_prompt(dt('Please enter the site name you would like to create or n to cancel'));
  if ($site_name = 'n') {
    return FALSE;
  }
  else {
    return $site_name;
  }
}

// TODO: Add this to the hook_command implementation above.
function drush_ignition_subcommand() {
  // Just planning the available subcommands.
  $subcommands = array(
    'setup-working-directory' => '',
    'get-code' => '',
    'create-database' => '',
    'sync-database' => '',
    'sync-files' => '',
  );
}

/**
 * Implements hook_drush_init().
 *
 * Register our autoloader.
 */
function ignition_drush_init() {
  $command = drush_get_command();
  // Check to see if we're running a Ignition command.
  if (strpos($command['command-hook'], 'ignition-') === 0) {
    spl_autoload_register('ignition_autoload');
  }
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

/**
 * A simple factory function for loading a service containter from the global Drush context.
 *
 * @return \Ignition\Utility\ServiceContainer.
 *   A service container populated from the global Drush context.
 */
function drush_ignition_get_service_container($handler = FALSE) {
  return \Ignition\Utility\ServiceContainer::getServiceContainerFromDrushContext();
}

/**
 * Retrieve a specific handler object.
 *
 * @param $type
 *   The type of handler we are loading (e.g. System, DB, etc.).
 * @param
 *   The specific handler to load, this should map directly to a class.
 * @return
 *   A loaded handler object.
 */
function drush_ignition_get_handler($type, $handler) {
  // TODO: Determine whether we could have a much better plugin system (hint:
  // we can).
  $handler = strtolower($handler);
  $handler[0] = strtoupper($handler[0]);
  $class = 'Ignition\\' . $type . '\\' . $handler;
  return new $class;
}

