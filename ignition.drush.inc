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

  $environment_options = array(
    'options' => array(
      'ignition-environment' => 'The type of server used (currently only supports Ubuntu).',
      'ignition-db' => 'The type of database to use (currently only supports MySQL).',
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
      'environment' => 'The name of the environment to load from.  Optional but required with database or file syncing.',
    ),
    'options' => array(
      'branch' => 'The branch of the repository to checkout',
      'tag' => 'The tag of the repository to checkout',
    ),
  );
  $items['ignition-get']['options'] = array_merge($items['ignition-get']['options'], $shared['options'], $environment_options['options']);

  $items['ignition-create'] = $environment_options + $shared + array(
    'description' => 'Setup a site on this machine similar to an Ignition site.  Note this will not add the site to the Ignition server.',
    'aliases' => array('ic'),
    'arguments' => array(
      'site' => 'The name of a site to create.',
    ),
    'options' => array(
      'version' => 'The version of the Drupal site to install (should be an integer, 6 & 7 are supported) 7 is the default.',
    ),
  );
  $items['ignition-create']['options'] = array_merge($items['ignition-create']['options'], $shared['options'], $environment_options['options']);

  $items['ignition-release'] = $environment_options + $shared + array(
    'description' => 'Release an Ignition managed site to a specific tag.',
    'aliases' => array('ir'),
    'arguments' => array(
      'site' => 'The name of the site to release.',
      'tag' => 'The tag to release.',
    ),
  );

  $items['ignition-delete'] = $environment_options + $shared + array(
    'description' => 'Remove an Ignition managed site from this server entirely.',
    'aliases' => array('id'),
    'arguments' => array(
      'site' => 'The name of the site to delete.',
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

function drush_ignition_create($site_name) {
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

  // Make sure that all hosting is properly configured on the server.
  $success = $site->setUp();

  // Checkout the site in the appropriate location.
  $success = $site->checkout();

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
    ->fetch();
}


function drush_ignition_delete($site_name) {
  if (drush_confirm()) {
    $site_info = new stdClass;
    $site_info->name = $site_name;
    $result = drush_ignition_get_site($site_info)->delete();
  }
}

/**
 * Factory function for creating a new site.
 */
function drush_ignition_get_site($site_info) {
  // TODO: Detect the system we are running.
  // Attempt to load a plugin appropriate to the system.
  $config['system'] = drush_ignition_get_handler('System', drush_get_option('ignition-environment'));
  // Attempt to load a plugin appropriate to the server.
  $config['server'] = drush_ignition_get_handler('Server', drush_get_option('ignition-server', 'Apache2'));
  // Load a database plugin.
  $config['database'] = drush_ignition_get_handler('DB', drush_get_option('ignition-db'));
  // Load the appropriate VCS plugin.
  $config['vcs'] = drush_ignition_get_handler('VCS', $site_info->vcs);
  // Add the loaded site information.
  $config['siteInfo'] = $site_info;
  return new Ignition\Site($config);
}

function drush_ignition_release() {
  // Optionally create a MySQL backup.
  // Checkout a new tag.
  // Create settings.php and files symlinks.
  // Update the webroot symlink.
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
    //->addParam('ignition-key', drush_get_option('ignition-key'))
    ->setMethod('GET')
    ->setTimeout(3)
    ->setFormat('json');
  // TODO: Add authentication.
  return $client;
}

/**
 * eseentially just stolen from Drupal 7's `drupal_random_bytes`.
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
  // Create the database
  // Load the appropriate VCS handler.
  $handler = strtolower($handler);
  $handler[0] = strtoupper($handler[0]);
  $class = 'Ignition\\' . $type . '\\' . $handler;
  return new $class;
}
