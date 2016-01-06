<?php

namespace Fetcher\Configurator\DrupalVersion;

use Fetcher\Configurator\ConfiguratorInterface,
    Fetcher\SiteInterface;

abstract class DrupalVersionBase {
  /**
   * If a value is an array convert it into a closure that returns an array.
   *
   * @param $originalValue - The orginal array to be wrapped if it is not already a function.
   * @return A closure that returns the value in question.
   */
  protected function normalizeConfigArrayToClosure($originalValue) {
    if (is_array($originalValue)) {
      $rawValue = $originalValue;
      $originalValue = function($site) use ($rawValue) {
        return $rawValue;
      };
    }
    return $originalValue;
  }

}
