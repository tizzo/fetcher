<?php
namespace Fetcher\Tests\Fixtures\Tasks;

class TaskAnnotationDependencyError {

  /**
   * @fetcherTask some_task_name 
   * @beforeTask foo
   * @afterTask baz
   */
  public function simpleMethod() {
    return 'This simpleMethod has run.';
  }
}
