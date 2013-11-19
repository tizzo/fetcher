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
    self::addTasksToStack($tasks, $stack, $site);
    $site->addTask($stack);
    $stack = new TaskStack('remove_site');
    $stack->description = 'Completely remove this site and destroy all data associated with it on the server.';
    $stack->afterMessage = 'The site `[[name]]` at `[[site.working_directory]]` has been completely removed!';
    $tasks = array(
      'remove_working_directory',
      'remove_drush_aliases',
      'remove_database',
      'remove_vhost',
    );
    self::addTasksToStack($tasks, $stack, $site);
    $site->addTask($stack);
  }

  static public function addTasksToStack($tasks, $stack, $site) {
    foreach ($tasks as $name) {
      $task = $site->getTask($name);
      if ($task) {
        $stack->addTask($task);
      }
      else {
        $site['log'](\sprintf('Task `%s` not found.', $name), 'warning');
      }
    }
  }

}
