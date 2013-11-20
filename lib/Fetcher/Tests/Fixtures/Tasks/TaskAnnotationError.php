<?php
namespace Fetcher\Tests\Fixtures\Tasks;

class TaskAnnotationError {
  /**
   * @fetcherTask some_name
   * @fetcherTask some_other_name
   * @description Something that sucks.
   */
  public function someMethod() {
    return 'Fail.';
  }
}


