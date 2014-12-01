<?php

namespace Fetcher\Configurator;

use Fetcher\SiteInterface;

class DrupalVersion implements ConfiguratorInterface {

  static public function configure(SiteInterface $site) {
    $class = '\Fetcher\Configurator\DrupalVersion\Drupal' . $site['version'];
    if (class_exists($class)) {
      $class::configure($site);
    }
  }

}
