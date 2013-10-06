<?php
namespace Fetcher\Tests\Fixtures\Tasks;

class TaskAnnotation {

  /**
   * @fetcher_task some_task_name 
   * @description Provides a sample task for parsing.
   * @before_message We are about to run a task.
   * @after_message We have just run a task.
   * @before_task foo
   * @before_task bar
   * @after_task baz
   */
  public function simpleMethod() {
    return 'This simpleMethod has run.';
  }
}
