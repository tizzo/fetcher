<?php

/**
 * Allows you to map drush context and options to site configuration.
 */
function hook_fetcher_option_key_mapping() {
  return array(
    'context' => array(
      'DRUSH_SIMULATE' => 'simulate',
      'DRUSH_VERBOSE' => 'verbose',
    ),
    'options' => array(
    ),
  );
}
