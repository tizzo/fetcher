<?php print '<?php' . PHP_EOL; ?>
ini_set('arg_separator.output',     '&amp;');
ini_set('magic_quotes_runtime',     0);
ini_set('magic_quotes_sybase',      0);
ini_set('session.cache_expire',     200000);
ini_set('session.cache_limiter',    'none');
ini_set('session.cookie_lifetime',  0);
ini_set('session.gc_maxlifetime',   200000);
ini_set('session.save_handler',     'user');
ini_set('session.use_cookies',      1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid',    0);
ini_set('url_rewriter.tags',        '');

$db_url = 'mysqli://<?php print $username; ?>:<?php print $password; ?>@<?php print $hostname; ?>/<?php print $database; ?>';

$conf['fetcher_environment'] = '<?php print $environment_local; ?>';
