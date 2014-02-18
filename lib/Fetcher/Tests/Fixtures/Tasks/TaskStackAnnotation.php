<?php
namespace Fetcher\Tests\Fixtures\Tasks;

class TaskStackAnnotation {

  /**
   * A function that is not a task.
   */
  public function notATask() {
    return 'This is not a task.';
  }

  /**
   * @fetcherTask first_stack_1 
   * @description Provides a sample task for parsing.
   * @stack test_stack_1
   */
  public function simpleMethodOne($site) {
    $site['log']('The first method from test_stack_1 has run.');
  }

  /**
   * @fetcherTask first_stack_2 
   * @description Provides a anther sample task for parsing.
   * @stack test_stack_1
   * @beforeTask first_stack_1
   */
  public function simpleMethodTwo($site) {
    $site['log']('The second method from test_stack_1 has run.');
  }
}
