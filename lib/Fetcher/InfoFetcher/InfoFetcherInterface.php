<?php

namespace Fetcher\InfoFetcher;

interface InfoFetcherInterface {
  public function getInfo($name);

  public function listSites($name = '', $page = 0, $options = array());
}
