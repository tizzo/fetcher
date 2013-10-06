<?php
namespace Fetcher\Tests\Fixtures\Tasks;

class TaskAnnotationError {
  /**
   * @fetcher_task some_name
   * @fetcher_task some_other_name
   * @description Something that sucks.
   */
  public function someMethod() {
    return 'Fail.';
  }
}


