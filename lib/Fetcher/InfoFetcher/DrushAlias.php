<?php

namespace Fetcher\InfoFetcher;
use Fetcher\InfoFetcher\InfoFetcherInterface;

class DrushAlias implements InfoFetcherInterface {

  /**
   * Get all available sites from aliases.
   */
  public function getSitesFromAliases() {
    $aliases = _drush_sitealias_find_and_load_all_aliases();
    $sites = array();
    foreach ($aliases as $name => $alias) {
      if (!empty($alias['fetcher'])) {
        $alias = $alias + $alias['fetcher'];
        unset($alias['fetcher']);
      }
      // If the alias has a `name` key set, we will presume it is fetcher-eligible.
      if (isset($alias['name'])) {
        $siteName = preg_replace('/(.*)\.(.*)/', '\1', $name);
        $environmentName = preg_replace('/(.*)\.(.*)/', '\2', $name);
        if (empty($sites[$siteName])) {
          $sites[$siteName] = $alias;
        }
        $sites[$siteName]['environments'][$environmentName] = $alias;
        $sites[$siteName]['environments'][$environmentName]['environment.remote'] = $environmentName;
      }
    }
    return $sites;
  }

  /**
   * Implements Fetcher\InfoFetcher\InfofetcherInterface::listSites().
   *
   * List all sites specidied in the drush aliases.
   */
  public function listSites($search = '', $page = 0, $options = array()) {
    $list = array();
    foreach ($this->getSitesFromAliases() as $name => $site) {
      if ($search == '' || ($search != '' && strpos($name, $search) !== FALSE)) {
        $list[$name] = $site;
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
