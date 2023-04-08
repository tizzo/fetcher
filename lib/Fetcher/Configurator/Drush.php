<?php

namespace Fetcher\Configurator;

use \Fetcher\SiteInterface,
    \Fetcher\Task\TaskStack;

class Drush implements ConfiguratorInterface {

  static public function configure(SiteInterface $site) {
    $drushOverrides = array();

    $drushOverrides['user prompt'] = $site->protect(function($message) {
      return \drush_prompt(\dt($message));
    });

    $drushOverrides['user confirm'] = $site->protect(function($message) {
      print 'running drush confirm';
      return \drush_confirm(\dt($message));
    });

    $drushOverrides['print'] = $site->protect(function($message) {
      \drush_print($message);
    });

    $drushOverrides['log.function'] = 'drush_log';
    $drushOverrides['log function'] = 'drush_log';

    foreach ($drushOverrides as $key => $value) {
      $site[$key] = $value;
    }

    // Note, this conifgurator is only intended for use with drush.
    $conf['name.global'] = $site->share(function($c) {
      return \drush_prompt(\dt('Please specify a site name'));
    });

    // Get the environment for this operation.
    $conf['environment.remote'] = function($c) {
      static $value = FALSE;
      if (!$value && !empty($c['environments'])) {
        $environments = $c['environments'];
        // If there is only 1 environment, use it.
        if (count($environments) == 0) {
          $value = FALSE;
        }
        if (count($environments) == 1) {
          $value = array_pop(array_keys($environments));
        }
        else if (count($environments) > 1) {
          $args = array('@envs' => implode(',', array_keys($environments)));
          // We create a new variable to prevent PHP from complaining about passing a non-variable by reference.
          $keys = array_keys($environments);
          $default = array_shift($keys);
          $value = drush_prompt(dt('Please select an environment (@envs).', $args), $default);
          $value = trim($value);
        }
        else {
          throw new \Fetcher\Exception\FetcherException('A valid environment could not be loaded');
        }
      }
      return $value;
    };
    $site->setDefaultConfigurations($conf);
  }
}

