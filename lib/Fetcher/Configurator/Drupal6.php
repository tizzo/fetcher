<?php

namespace Fetcher\Configurator;

use \Fetcher\SiteInterface,
    \Fetcher\Task\TaskStack;

class Drupal6 implements ConfiguratorInterface {

  static public function configure(SiteInterface $site) {
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
    $site['settings_php.database_variable'] = function($c) {
      return 'mysqli://' . $c['database.user.name'] . ':' . $c['database.user.password'] . '@' . $c['database.hostname'] . '/' . $c['database.database'];
    };
  }

$db_url = 

$conf['fetcher_environment'] = '<?php print $environment_local; ?>';
  }
}
 
