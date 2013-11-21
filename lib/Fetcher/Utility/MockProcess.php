<?php

/**
 * @file
 *   A MockProcess object to use when `simulate` is enabled.
 *
 * TODO: Integrate this more deeply, possibly at the configurator level.
 */

namespace Fetcher\Utility;


class MockProcess {

  public function run() {
  }

  public function isSuccessful() {
    return TRUE;
  }

}


