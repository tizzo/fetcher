<?php

namespace Ignition\InfoFetcher;

class DrushAlias  implements InfoFetcherInterface {

  /**
   *
   */
  public function listSites($name = '', $page = 0) {
    // TODO: Add name searching.
    $aliases = _drush_sitealias_find_and_load_all_aliases();
    $sites = array();
    $list = array();
    // TODO: We need to handle multiple environments here.
    foreach ($aliases as $name => $alias) {
      if (!empty($alias['ignition'])) {
        $info = $alias['ignition'];
        if (!empty($list[$info['name']])) {
          drush_print_r($list);
          $info = (object) array_merge((array) $list[$info['name']], $info);
        }
        $list[$info['name']] = (object) $info;
      }
    }
    return $list;
  }

  public function getInfo($name) {
    $sites = $this->listSites($name);
    if (isset($sites[$name])) {
      return $sites[$name];
    }
  }
}
