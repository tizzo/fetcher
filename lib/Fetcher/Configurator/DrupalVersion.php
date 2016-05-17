<?php

namespace Fetcher\Configurator;

use Fetcher\SiteInterface;

class DrupalVersion implements ConfiguratorInterface {

  static public function configure(SiteInterface $site) {
    // Here version can be something like 8.1 or 7.x-dev but fetcher needs to know the major version.
    $class = '\Fetcher\Configurator\DrupalVersion\Drupal' . substr($site['version'], 0, 1);
    if (class_exists($class)) {
      $class::configure($site);
    }
  }

}
