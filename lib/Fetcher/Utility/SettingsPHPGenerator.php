<?php

/**
 * @file
 *   Provide utility functions for generating PHP strings from data structures.
 */

namespace Fetcher\Utility;


class SettingsPHPGenerator {

  // An associative array of setting => value.
  private $iniSettings = array();

  // A linear array of files to require.
  private $requires = array();

  // An associative array of variables to create keyed by name.
  private $variables = array();

  /**
   * Set a bucket or a variable within a bucket.
   */
  public function set($bucket, $values, $key = NULL) {
    if (!$this->bucketNameIsValid($bucket)) {
      throw new \Exception('Invalid bucket specified');
    }
    if ($key !== NULL) {
      $this->{$bucket}[$key] = $values;
    }
    else {
      $this->$bucket = $values;
    }
  }

  /**
   * Get a bucket or a variable within a bucket.
   *
   * @param $bucket
   *    The name of the bucket (iniSettings, requires or variables).
   * @param $key
   *    The key (if set).
   */
  public function get($bucket, $key = NULL) {
    if (!$this->bucketNameIsValid($bucket)) {
      throw new \Exception('Invalid bucket specified');
    }
    if ($key !== NULL) {
      return $this->{$bucket}[$key];
    }
    return $this->$bucket;
  }

  /**
   * Compile the string for the settings.php file.
   */
  public function compile($includeTag = true) {
    $output = $includeTag ? array('<?php') : array();

    if (!empty($this->iniSettings)) {
      $output[] = '';
      foreach ($this->iniSettings as $name => $value) {
        $output[] = 'ini_set(\'' . $name . '\', ' . $this->getString($value) . ');';
      }
    }

    if (!empty($this->variables)) {
      $output[] = '';
      foreach ($this->variables as $name => $value) {
        $output[] = '$' . $name . ' = ' . $this->getString($value) . ';';
      }
    }

    if (!empty($this->requires)) {
      $output[] = '';
      foreach ($this->requires as $value) {
        $output[] = 'require_once(\'' . $value . '\');';
      }
    }

    return implode(PHP_EOL, $output);
  }

  /**
   * Get a string representation of php data.
   *
   * @param $data
   *   Data to be converted into a php code string representation.
   * @return
   *   A string representation of the data to be embedded in php code.
   */
  public function getString($data) {
    $output = '';
    if (is_object($data)) {
      throw new \Exception('Object values not supported in settings.php generation.');
    }
    else if (is_array($data)) {
      $array = $data;
      $output = '';
      PHPGenerator::arrayExport($array, $output);
    }
    else if (is_numeric($data)) {
      $output = $data;
    }
    else {
      $output = '\'' . \addslashes($data) . '\'';
    }
    return $output;
  }

  /**
   * Verify that this is a valid bucket.
   *
   * @param $name
   *   A bucket name for which to test validity.
   */
  private function bucketNameIsValid($name) {
    return in_array($name, array('iniSettings', 'requires', 'variables'));
  }
}
