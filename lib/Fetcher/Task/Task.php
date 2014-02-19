<?php

namespace Fetcher\Task;
use Symfony\Process;

class Task extends AbstractTask implements TaskInterface {

  /**
   * Run the internal logic for this task.
   */
  function performAction($site, $arguments) {
    if (empty($this->callable)) {
      throw new TaskRunException('No callable was assigned to the task before running.');
    }
    \call_user_func_array($this->callable, array($site) + $arguments);
  }


}
