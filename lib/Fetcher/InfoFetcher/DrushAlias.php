<?php

namespace Fetcher\InfoFetcher;
use Fetcher\InfoFetcher\InfoFetcherInterface;

class DrushAlias implements InfoFetcherInterface {

  /**
   * @param $site
   *  An array of site information from drush aliases.
   */
  public function getEnvironmentRedundancies($site) {
    $redundancies = array();
    foreach ($site['environments'] as $environment) {
      foreach ($environment as $key => $value) {
        $redundant = TRUE;
        $oldValue = $environment[$key];
        foreach ($site['environments'] as $envToCheck) {
          if ($envToCheck[$key] !== $oldValue) {
            $redundant = FALSE;
            $oldValue = $envToCheck[$key];
            break;
          }
          $oldValue = $envToCheck[$key];
        }
        if ($redundant && !in_array($key, $redundancies)) {
          $redundancies[] = $key;
        }
      }
    }
    return $redundancies;
  }

  /**
   * Get all available sites from aliases.
   */
  public function getSitesFromAliases() {
    $aliases = array();
    // Here we use the method used by drush_sitealias_print (the guts of `drush sa`).
    foreach (_drush_sitealias_all_list() as $site => $aliasRecord) {
      $siteName = substr($site, 1);
      $aliases[$siteName] = _drush_sitealias_find_and_load_alias($siteName);
    }
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
        if (!isset($sites[$siteName])) {
          $sites[$siteName] = array();
        }
        // Build up the site data.
        $sites[$siteName] = $sites[$siteName] + $alias;
        $sites[$siteName]['environments'][$environmentName] = $alias;
        $sites[$siteName]['environments'][$environmentName]['environment.remote'] = $environmentName;
        // We already have an alias for this site, so specify the file.
        $sites[$siteName]['drush_alias.path'] = $alias['#file'];
      }
    }
    foreach ($sites as $siteName => $site) {
      // To keep our data clean we only want to set keys that are actually
      // different from environment to environment on the environment level.
      if (!empty($sites[$siteName]['environments'])) {
        // Find keys/value pairs common to all aliases, for removal.
        $environmentRedundancies = $this->getEnvironmentRedundancies($sites[$siteName]);
        foreach ($sites[$siteName] as $key => $value) {
          // Remove drush private attributes.
          if (strpos($key, '#') === 0) {
            unset($sites[$siteName][$key]);
          }
          foreach ($sites[$siteName]['environments'] as &$environment) {
            // Remove drush private attributes.
            if (strpos($key, '#') === 0) {
              unset($environment[$key]);
            }
            if (in_array($key, $environmentRedundancies)) {
              //drush_print('unsetting ' .$key);
              unset($environment[$key]);
            }
          }
        }
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
    ksort($list);
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
