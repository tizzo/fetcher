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
      'ensure_code',
      'ensure_settings_file',
      'ensure_sym_links',
      'ensure_drush_alias',
      'ensure_database_connection',
      'ensure_site_info_file',
      'ensure_server_host_enabled',
    );
    foreach ($tasks as $name) {
      $task = $site->getTask($name);
      if ($task) {
        $stack->addTask($task);
      }
      else {
        $site['log'](\sprintf('Task `%s` not found.', $name), 'warning');
      }
    }
    $site->addTask($stack);
  }

}
