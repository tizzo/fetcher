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
 * Issue a request 
 */
function drush_ignition_get_request_handler() {
  $client = new Ignition\Utility\RESTClient();
  $client->setURL('http://extranet.local')
    ->setFormat('json');
  // TODO: Add authentication.
  // $client->addParam('ignition_key': $key);
  return $client;
}
