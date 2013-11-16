<?php

namespace Fetcher\Configurator;

use \Fetcher\SiteInterface,
    \Fetcher\Task\TaskStack;

class DrushPrompts implements ConfiguratorInterface {

  static public function configure(SiteInterface $site) {
    // Note, this conifgurator is only intended for use with drush.
    $site['name.global'] = $site->share(function($c) {
      return \drush_prompt(\dt('Please specify a site name'));
    });
    $site['log.function'] = 'drush_log';

    // Get the environment for this operation.
    $site['environment.remote'] = function($c) {
      static $value = FALSE;
      if (!$value && !empty($c['environments'])) {
        $environments = $c['environments']; 
        // If there is only 1 environment, use it.
        if (empty($environment)) {
          $value = FALSE;
        }
        if (count($environments) == 1) {
          $value = $environments[0];
        }
        else if (count($environments) > 1) {
          $args = array('@envs' => implode(',', array_keys($environments)));
          $default = array_pop(array_keys($environments));
          $value = drush_prompt(dt('Please select an environment (@envs).', $args), $default);
          $value = trim($value);
        }
        else {
          throw new \Fetcher\Exception\FetcherException('A valid environment could not be loaded');
        }
      }
      return $value;
    };
  }
}
 
