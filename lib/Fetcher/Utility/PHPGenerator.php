<?php

/**
 * @file
 *   Provide utility functions for generating PHP strings from data structures.
 */

namespace Fetcher\Utility;


class PHPGenerator {

  /**
   * Export an array as executable PHP code.
   *
   * @param (Array) $data
   *  The array to be exported.
   * @param (string) $string
   *  The string to add to this array to.
   * @param (int) $indentLevel
   *  The level of indentation this should be run at.
   */
  static public function arrayExport(Array $data, &$string, $indentLevel) {
    $i = 0;
    $indent = '';
    while ($i < $indentLevel) {
      $indent .= '  ';
      $i++;
    }
    $string .= "array(" . PHP_EOL;
    foreach ($data as $name => $value) {
      $string .= "$indent  '$name' => ";
      if (is_array($value)) {
        $inner_string = '';
        $string .= self::arrayExport($value, $inner_string, $indentLevel + 1) . "," . PHP_EOL;
      }
      else if (is_numeric($value)) {
        $string .= "$value," . PHP_EOL;
      }
      else if (is_string($value)) {
        $string .= "'" . str_replace("'", "\'", $value) . "'," . PHP_EOL;
      }
      else if (is_null($value)) {
        $string .= 'NULL,' . PHP_EOL;
      }
    }
    $string .= "$indent)";
    return $string;
  }

}
