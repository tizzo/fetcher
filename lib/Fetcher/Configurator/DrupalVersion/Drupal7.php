<?php

namespace Fetcher\Configurator\DrupalVersion;

use Fetcher\Configurator\ConfiguratorInterface,
    Fetcher\SiteInterface;

class Drupal7 implements ConfiguratorInterface {

  static public function configure(SiteInterface $site) {

    $site['settings_php.ini_set'] = array(
      'arg_separator.output' => '&amp;',
      'magic_quotes_runtime' => 0,
      'magic_quotes_sybase' => 0,
      'session.cache_expire' => 200000,
      'session.cache_limiter' => 'none',
      'session.cookie_lifetime' => 0,
      'session.gc_maxlifetime' => 200000,
      'session.save_handler' => 'user',
      'session.use_cookies' => 1,
      'session.use_only_cookies' => 1,
      'session.use_trans_sid' => 0,
      'url_rewriter.tags' => '',
    );

    $variables = $site['settings_php.variables'];
    $site['settings_php.variables'] = function($c) use ($variables) {
      return $variables + array(
        'databases' => array(
          'default' => array(
            'default' => array(
              'database' => $c['database.database'],
              'username' => $c['database.user.name'],
              'password' => $c['database.user.password'],
              'host' => $c['database.hostname'],
              'port' => $c['database.port'],
              'driver' => $c['database']->getDriver(),
              'prefix' => $c['database.prefix'],
            ),
          ),
        ),
      );
    };
  }

}
