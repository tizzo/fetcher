<?php
namespace Fetcher\Tests\Fixtures\Tasks;

class TaskAnnotation {

  /**
   * A function that is not a task.
   */
  public function notATask() {
    return 'This is not a task.';
  }

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
