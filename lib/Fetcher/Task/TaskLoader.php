<?php

namespace Fetcher\Task;
use Symfony\Process;

class TaskLoader {

  /**
   * Tasks that have been loaded.
   */
  private $tasks = array();

  /**
   * Set the array of currently available tasks.
   *
   * @param $tasks
   *   An array of tasks implementing the TaskInterface taken by reference.
   */
  public function setTasks(&$tasks) {
    $this->tasks =& $tasks;
  }

  /**
   * Get the array of currently available tasks
   */
  public function getTasks() {
    return $this->tasks;
  }

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
    return $this->scanClass(\get_class($object), $object);
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
    $tasks =& $this->tasks;
    $reflection = new \ReflectionClass($class);
    foreach ($reflection->getMethods() as $method) {
      $annotations = $this->parseAnnotations($method->getDocComment());
      $this->createTaskStacksFromAnnotations($annotations);
      if ($task = $this->parseTaskInfo($annotations)) {
        $tasks[$task->fetcherTask] = $task;
        if (!$method->isStatic() && !empty($instance)) {
          $task->callable = array($instance, $method->getName());
        }
        else {
          $task->callable = array($class, $method->getName());
        }
      }
    }
    $this->tasks = $tasks;
    return $this->tasks;
  }

  /**
   * Scan all functions defined in a file for fetcher tasks.
   *
   * @param $file
   *   The path to the file to include.
   */
  public function scanFunctionsInFile($file) {
    if (!file_exists($file)) {
      throw new TaskLoaderException(sprintf('Attempted to scan file at %s but it does not exist.', $file));
    }
    $existingFunctions = get_defined_functions();
    include $file;
    $currentFunctions = get_defined_functions();
    $functionsDiff = array_diff($currentFunctions['user'], $existingFunctions['user']);
    return $this->scanFunctions($functionsDiff);
  }

  /**
   * Scan all user space functions.
   */
  public function scanAllUserSpaceFunctions() {
    $existingFunctions = get_defined_functions();
    return $this->scanFunctions($existingFunctions['user']);
  }

  /**
   *
   * @param $functions
   *   An array of functions to scan for fetcherTask annotations
   * @return
   *  An array of loaded fetcher tasks.
   */
  public function scanFunctions(Array $functions) {
    $tasks =& $this->tasks;
    foreach($functions as $functionName) {
      $function = new \ReflectionFunction('\\' . $functionName);
      $annotations = $this->parseAnnotations($function->getDocComment());
      $this->createTaskStacksFromAnnotations($annotations);
      if ($task = $this->parseTaskInfo($annotations)) {
        $task->callable = $functionName;
        $tasks[$task->fetcherTask] = $task;
      }
    }
    return $tasks;
  }

  /**
   *
   */
  public function createTaskStacksFromAnnotations(&$annotations) {
    if (!empty($annotations['stacks'])) {
      foreach ($annotations['stacks'] as $stack => $dependencies) {
        if (empty($this->tasks[$stack])) {
          $this->tasks[$stack] = new TaskStack($stack);
        }
      }
      unset($annotations['stacks']);
    }
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
      'stack',
    );
    foreach ($multiValueAttributes as $attribute) {
      if (!empty($annotations[$attribute])) {
        $task->{$attribute} = $annotations[$attribute];
      }
    }
    return $task;
  }

  /**
   * Origintally stolen from phpunit.
   *
   * @param  string $docblock
   * @return array
   */
  public function parseAnnotations($docblock) {
    $annotations = array();
    if (preg_match_all('/@(?P<name>[A-Za-z_-]+)(?:[ \t]+(?P<value>.*?))?[ \t]*\r?$/m', $docblock, $matches)) {
      $numMatches = count($matches[0]);
      $currentStack = NULL;
      for ($i = 0; $i < $numMatches; ++$i) {
        $name = $matches['name'][$i];
        $value = $matches['value'][$i];
        switch ($name) {
          case 'stack':
            $annotations['stacks'][$value] = array();
            $currentStack = $value;
            break;
          case 'beforeTask':
          case 'afterTask':
            if (!is_null($currentStack)) {
              $annotations['stacks'][$value][$name][] = $value;
            }
            break;
          default:
            $annotations[$name][] = $matches['value'][$i];
            break;
        }
      }
    }
    return $annotations;
  }

}
