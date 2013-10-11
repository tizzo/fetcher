<?php

namespace Fetcher\Configurator;

use \Fetcher\SiteInterface,
    \Fetcher\Task\TaskStack;

class DefaultTaskStacks implements ConfiguratorInterface {

  static public function configure(SiteInterface $site) {
    $stack = new TaskStack('ensure_site');
    $stack->description = 'Ensure that a site is properly configured to run on this server.';
    $stack->afterMessage = 'Your site is setup and is now running at [[hostname]]!';
    $tasks = array(
      'ensure_working_directory',
      'ensure_site_info_file',
      'ensure_code',
      'ensure_database_connection',
      'ensure_settings_file',
      'ensure_symlinks',
      'ensure_drush_alias',
      'ensure_server_host_enabled',
    );
    foreach ($tasks as $task) {
      $task = $site->getTask($task);
      if ($task) {
        $stack->addTask($task);
      }
    }
    $site->addTask($stack);
  }

}
