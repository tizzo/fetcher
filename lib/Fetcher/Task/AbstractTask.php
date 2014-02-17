<?php

namespace Fetcher\Task;

abstract class AbstractTask implements TaskInterface {

  /**
   * Constructor.
   *
   * @param $fetcher_task
   *   The machine name for this task.
   */
  public function __construct($fetcherTask = NULL) {
    $this->fetcherTask = $fetcherTask;
  }



  /**
   * Run this task.
   */
  public function run($site, $arguments = array()) {
    if (!empty($this->beforeMessage)) {
      $site['log']($this->prepMessage($this->beforeMessage, $site), 'ok');
    }
    $this->performAction($site, $arguments);
    if (!empty($this->afterMessage)) {
      $site['log']($this->prepMessage($this->afterMessage, $site), 'ok');
    }
  }


  abstract function performAction($site, $arguments);

}
