<?php

namespace Fetcher\InfoFetcher;
use Fetcher\InfoFetcher\InfoFetcherInterface;

class DrushAlias implements InfoFetcherInterface {

  /**
   * Implements Fetcher\InfoFetcher\InfofetcherInterface::listSites().
   *
   * List all sites specidied in the drush aliases.
   */
  public function listSites($name = '', $page = 0, $options = array()) {
    // TODO: Add name searching.
    $aliases = _drush_sitealias_find_and_load_all_aliases();
    $sites = array();
    $list = array();
    // TODO: We need to handle multiple environments here.
    foreach ($aliases as $name => $alias) {
      if (!empty($alias['fetcher'])) {
        $alias = $alias + $alias['fetcher'];
        unset($alias['fetcher']);
      }
      // If the alias has a `name` key set, we will presume it is fetcher-eligible.
      if (isset($alias['name'])) {
        $siteName = preg_replace('/(.*)\.(.*)/', '\1', $name);
        $environmentName = preg_replace('/(.*)\.(.*)/', '\2', $name);
        $list[$siteName]['environments'][$environmentName] = $alias;
        $list[$siteName]['environments'][$environmentName]['environment.remote'] = $environmentName;
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
