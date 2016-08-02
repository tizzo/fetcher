<Directory <?php print $docroot; ?> >
  Options FollowSymLinks
  AllowOverride None
  # Protect files and directories from prying eyes.
  <FilesMatch "\.(engine|inc|install|make|module|profile|po|sh|.*sql|theme|twig|tpl(\.php)?|xtmpl|yml)(~|\.sw[op]|\.bak|\.orig|\.save)?$|^(\..*|Entries.*|Repository|Root|Tag|Template|composer\.(json|lock))$|^#.*#$|\.php(~|\.sw[op]|\.bak|\.orig|\.save)$">
    <IfModule mod_authz_core.c>
      Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
      Order allow,deny
    </IfModule>
  </FilesMatch>

  # Make Drupal handle any 404 errors.
  ErrorDocument 404 /index.php

  # PHP 5, Apache 1 and 2.
  <IfModule mod_php5.c>
    php_flag session.auto_start               off
    php_value mbstring.http_input             pass
    php_value mbstring.http_output            pass
    php_flag mbstring.encoding_translation    off
  </IfModule>

  # Requires mod_expires to be enabled.
  <IfModule mod_expires.c>
    # Enable expirations.
    ExpiresActive On

    # Cache all files for 2 weeks after access (A).
    ExpiresDefault A1209600

    <FilesMatch \.php$>
      # Do not allow PHP scripts to be cached unless they explicitly send cache
      # headers themselves. Otherwise all scripts would have to overwrite the
      # headers set by mod_expires if they want another caching behavior. This may
      # fail if an error occurs early in the bootstrap process, and it may cause
      # problems if a non-Drupal PHP file is installed in a subdirectory.
      ExpiresActive Off
    </FilesMatch>
  </IfModule>

  <Files "cron.php">
    Order Deny,Allow
    Deny from all
    Allow from localhost
    Allow from 127.0.0.1
  </Files>

  # Rewrite URLs of the form 'index.php?q=x'.
  RewriteEngine On
  RewriteRule ^ - [E=protossl]
  RewriteCond %{HTTPS} on
  RewriteRule ^ - [E=protossl:s]
  RewriteRule "(^|/)\." - [F]
  RewriteBase /
  RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
  RewriteCond %{REQUEST_URI} ^(.*)?/(install.php) [OR]
  RewriteCond %{REQUEST_URI} ^(.*)?/(rebuild.php)
  RewriteCond %{REQUEST_URI} !core
  RewriteRule ^ %1/core/%2 [L,QSA,R=301]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_URI} !=/favicon.ico
  RewriteRule ^ index.php [L]
  RewriteCond %{REQUEST_URI} !/core/[^/]*\.php$
  RewriteCond %{REQUEST_URI} !/core/modules/system/tests/https?.php$
  RewriteCond %{REQUEST_URI} !/core/modules/statistics/statistics.php$
  RewriteRule "^.+/.*\.php$" - [F]

  # Rules to correctly serve gzip compressed CSS and JS files.
  # Requires both mod_rewrite and mod_headers to be enabled.
  <IfModule mod_headers.c>
    # Serve gzip compressed CSS files if they exist and the client accepts gzip.
    RewriteCond %{HTTP:Accept-encoding} gzip
    RewriteCond %{REQUEST_FILENAME}\.gz -s
    RewriteRule ^(.*)\.css $1\.css\.gz [QSA]

    # Serve gzip compressed JS files if they exist and the client accepts gzip.
    RewriteCond %{HTTP:Accept-encoding} gzip
    RewriteCond %{REQUEST_FILENAME}\.gz -s
    RewriteRule ^(.*)\.js $1\.js\.gz [QSA]

    # Serve correct content types, and prevent mod_deflate double gzip.
    RewriteRule \.css\.gz$ - [T=text/css,E=no-gzip:1]
    RewriteRule \.js\.gz$ - [T=text/javascript,E=no-gzip:1]

    <FilesMatch "(\.js\.gz|\.css\.gz)$">
      # Serve correct encoding type.
      Header set Content-Encoding gzip
      # Force proxies to cache gzipped & non-gzipped css/js files separately.
      Header append Vary Accept-Encoding
    </FilesMatch>
  </IfModule>
</Directory>
<VirtualHost *:<?php print $port; ?>>
  ServerName <?php print $hostname . PHP_EOL;  ?>
  DocumentRoot <?php print $docroot . PHP_EOL; ?>
  LogLevel warn
  ServerSignature Off
</VirtualHost>
