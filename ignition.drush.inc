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

  $items['ignition'] = array(
    'description' => 'This is my thing',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'aliases' => array('i'),
  ) + $shared;

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


function ignition_drush_init() {
  $command = drush_get_command();
  drush_print_r($command);
}

