<?php
namespace Fetcher\Tests\Fixtures\Tasks;

class TaskAnnotation {

  /**
   * @fetcherTask some_task_name 
   * @description Provides a sample task for parsing.
   * @beforeMessage We are about to run a task.
   * @afterMessage We have just run a task.
   * @beforeTask foo
   * @beforeTask bar
   * @afterTask baz
   */
  public function simpleMethod() {
    return 'This simpleMethod has run.';
  }
}
