<?php

namespace Fetcher\Task;
use Symfony\Process;

class TaskLoader {

  /**
   * Scan an instantiated object for task methods.
   *
   * A convenience wrapper around TaskLoader::scanClass().
   *
   * @param $object
   *   An instantiated object to scan for task methods.
   * @return
   *   An array of tasks.
   */
  public function scanObject($object) {
    return $this->scanClass(\get_class($object));
  }

  /**
   * Scan a class extracting task annotations.
   *
   * @param $class
   *   A string containing the name of a class to scan.
   * @param $instance
   *   If we have an instantiated object, use it this to generate the task callable.
   * @return
   *   An array of tasks.
   */
  public function scanClass($class, $instance = NULL) {
    $tasks = array();
    $reflection = new \ReflectionClass($class);
    foreach ($reflection->getMethods() as $method) {
      $annotations = $this->parseAnnotations($method->getDocComment());
      if (!$method->isStatic() && !empty($instance)) {
        $task->callable = array($instance, $method->getName());
      }
      else {
        $task->callable = array($class, $method->getName());
      }
      if ($task = $this->parseTaskInfo($annotations)) {
        $tasks[$task->fetcherTask] = $task;
      }
    }
    return $tasks;
  }

  /**
   * Parse the contents of a single annotation.
   *
   * @param $annotations
   *   Annotations of the form returned from TaskLoader::parseAnnotations().
   * @return
   *   A task definition.
   */
  public function parseTaskInfo(Array $annotations) {
    if (empty($annotations['fetcherTask'])) {
      return FALSE;
    }
    if (count($annotations['fetcherTask']) !== 1) {
      throw new TaskLoaderException('Exactly one task must be specified');
    }
    $task = new Task();
    $singleValueAttributes = array(
      'fetcherTask',
      'description',
      'beforeMessage',
      'afterMessage',
    );
    foreach ($singleValueAttributes as $attribute) {
      if (!empty($annotations[$attribute])) {
        $task->{$attribute} = $annotations[$attribute][0];
      }
    }
    $multiValueAttributes = array(
      'beforeTask',
      'afterTask',
      'stacks',
    );
    foreach ($multiValueAttributes as $attribute) {
      if (!empty($annotations[$attribute])) {
        $task->{$attribute} = $annotations[$attribute];
      }
    }
    return $task;
  }

  /**
   * Stolen from phpunit.
   *
   * @param  string $docblock
   * @return array
   */
  function parseAnnotations($docblock) {
    $annotations = array();

    if (preg_match_all('/@(?P<name>[A-Za-z_-]+)(?:[ \t]+(?P<value>.*?))?[ \t]*\r?$/m', $docblock, $matches)) {
      $numMatches = count($matches[0]);

      for ($i = 0; $i < $numMatches; ++$i) {
        $annotations[$matches['name'][$i]][] = $matches['value'][$i];
      }
    }

    return $annotations;
  }

}
