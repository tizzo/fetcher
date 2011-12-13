<?php

function ignition_drush_help($section) {
  switch ($section) {
    case 'meta:ignition:title':
      return dt('Ignition commands');
    case 'meta:ignition:summary':
      return dt('Interact locally with sites managed by Ignition.');
  }
}

function ignition_drush_command() {
  $items = array();

  $shared = array(
    // 'callback' => 'dog_main',
    // 'callback required prefix' => 'dog_',
    // 'callback arguments' => 'normal',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
  );

  $items['ignition-list'] = array(
    'description' => 'List all sites managed by Ignition.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'aliases' => array('il'),
    'options' => array(
      'hostname' => 'The hostname of the ignition server.',
    ),
  ) + $shared;

  return $items;
}

/**
 * Autoloader for initialization (init & rollout) dog commands.
 *
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

function ignition_drush_init() {
  spl_autoload_register('ignition_autoload');
}


/**
 * List sites in the ignition install.
 */
function drush_ignition_list() {
  $rest = new Ignition\External\RESTRequest();
  $rest->url = 'http://192.168.56.136:8081';
  $rest->path = 'getObject';
  $result = $rest
    ->addParam('path', '/var/www/repoview/webroot/sites/all/modules/repoview/.git')
    ->addParam('objectPath', 'plugins')
    ->execute()
    ->decode();
  drush_print_r($result);
}
