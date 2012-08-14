<?php

namespace Fetcher\InfoFetcher;
use Fetcher\InfoFetcher\InfoFetcherInterface;

class DrushAlias  implements InfoFetcherInterface {

  /**
   * Implements Fetcher\InfoFetcher\InfofetcherInterface::listSites().
   *
   * List all sites specidied in the drush aliases.
   */
  public function listSites($name = '', $page = 0) {
    // TODO: Add name searching.
    $aliases = _drush_sitealias_find_and_load_all_aliases();
    $sites = array();
    $list = array();
    // TODO: We need to handle multiple environments here.
    foreach ($aliases as $name => $alias) {
      if (!empty($alias['fetcher'])) {
        $info = $alias['fetcher'];
        if (!empty($list[$info['name']])) {
          $info = ((array) $list[$info['name']] + $info);
        }
        $list[$info['name']] = $info;
      }
    }
    return $list;
  }

  /**
   * Implements Fetcher\InfoFetcher\InfofetcherInterface::getInfo().
   */
  public function getInfo($name) {
    $sites = $this->listSites($name);
    if (isset($sites[$name])) {
      return $sites[$name];
    }
  }

}
