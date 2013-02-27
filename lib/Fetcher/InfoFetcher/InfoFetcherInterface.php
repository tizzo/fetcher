<?php

namespace Fetcher\InfoFetcher;

interface InfoFetcherInterface {

  /**
   * Retrieve all information about a site by machine name.
   *
   * @param $name
   */
  public function getInfo($name);

  /**
   * List all sites in the info fetcher.
   *
   * @param $name
   *   The name of the site or the beginning of the name of the site.
   *   This parameter is assumed to be the first part of a wildcard.
   * @param $page
   *   The page of information to show.
   * @param $options
   *   An array of options to further filter the query.
   *   Any given filter may be respected or ignored by a given InfoFetcher class.
   */
  public function listSites($name = '', $page = 0, $options = array());

}
