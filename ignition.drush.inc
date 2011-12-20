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

  $shared = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'options' => array(
      'ignition-server' => 'The URL of the ignition server.',
      'ignition-key' => 'The URL of the ignition server.',
    ),
  );

  $items['ignition-list'] = $shared + array(
    'description' => 'List all sites managed by Ignition.',
    'aliases' => array('il'),
    'arguments' => array(
      'search' => 'The name of a site to search for.',
    ),
  );

  $items['ignition-get'] = $shared + array(
    'description' => 'Setup a site on this machine managed by Ignition.',
    'aliases' => array('ig'),
    'arguments' => array(
      'site' => 'The name of a site to search install.',
    ),
  );

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
  if (!$client) {
    return;
  }
  $client->setPath('ignition/api/site.json')
    ->setMethod('GET')
    ->setTimeout(3);
  if ($name != '') {
    $client->addParam('name', $name);
  }
  $result = $client->fetch();
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
 * Do all of the
 */
function drush_ignition_get($site_name) {

  // TODO: Document installation instructions.

  $client = drush_ignition_get_request_handler();
  $site_info = $client->setPath("ignition/api/site/$site_name.json")
    ->setMethod('GET')
    ->setTimeout(3)
    ->fetch();
  if ($site_info === FALSE) {
    // TODO: Check for connectivity problems.
    //if ($client->meta
    drush_log('This site does not appear to exist.', 'error');
    return;
  }
  drush_print_r($site_info);

  // Detect the system we are running.
  // TODO: Determine the plugin system.

  // Attempt to load a plugin appropriate to the system.
  $system = new Ignition\System\Ubuntu();

  // Determine the path to use.
  $webroot = $system->getWebRoot();
  $working_directory = $webroot . '/' . $site_name;

  // Determine whether we have created this site in the past.
  $create = !is_dir($working_directory);

  // Create the working directory if it does not already exist.
  $system->ensureFolderExists($working_directory);

  if ($create) {
    $mysql->createDatabase();
  }

/*
  sql = "mysql -e 'create database %s'" % (siteName)
  subprocess.call(sql, shell=options.verbose)
  # Generate a random password
  password = randomPassword.makeRandomPassword()
  sql = "mysql -e 'create user \"%s\"@\"localhost\" identified by \"%s\"'" % (siteName, password)
  subprocess.call(sql, shell=options.verbose)
  sql = "mysql -e 'grant all on %s.* to \"%s\"@\"localhost\"'" % (siteName, siteName)
  subprocess.call(sql, shell=options.verbose)
  sql = "mysql -e 'flush privileges'"
  subprocess.call(sql, shell=options.verbose)
  */


  // Create the database
  // Load the appropriate VCS handler.

  // TODO: Create settings for user:group that should own the code & files
  // directories.
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
  $client->setURL(drush_get_option('ignition-server'))
    ->addParam('ignition-key', drush_get_option('ignition-key'))
    ->setFormat('json');
  // TODO: Add authentication.
  return $client;
}
