<?php

namespace Fetcher\Configurator;

use \Fetcher\SiteInterface;

interface ConfiguratorInterface {
  static public function configure(SiteInterface $site);
}
