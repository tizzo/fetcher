<?php

namespace Fetcher\Configurator\DrupalVersion;

use Fetcher\Configurator\ConfiguratorInterface,
    Fetcher\SiteInterface;

class Drupal6 extends DrupalVersionBase implements ConfiguratorInterface {

  static public function configure(SiteInterface $site) {

    // Drupal 6 only came with `default` profile.
    $site['profile'] = 'default';

    $site['settings_php.ini_set'] = array(
      'arg_separator.output' => '&amp;',
      'magic_quotes_runtime' => 0,
      'magic_quotes_sybase' => 0,
      'session.cache_expire' => 2000000,
      'session.cache_limiter' => 'none',
      'session.cookie_lifetime' => 2000000,
      'session.gc_maxlifetime' => 200000,
      'session.save_handler' => 'user',
      'session.use_cookies' => 1,
      'session.use_only_cookies' => 1,
      'session.use_trans_sid' => 0,
      'url_rewriter.tags' => '',
    );

    $originalVariables = self::normalizeConfigArrayToClosure($site->raw('settings_php.variables'));
    $site['settings_php.variables'] = function($c) use ($originalVariables) {
      $db_url = 'mysqli://' . $c['database.user.name'] . ':' . $c['database.user.password'] . '@' . $c['database.hostname'] . '/' . $c['database.database'];
      return $originalVariables($c) + array('db_url' => $db_url);
    };
  }
}
 
