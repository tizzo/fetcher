<Directory /var/www/<?php print $site_name; ?>/webroot/>
  Options FollowSymLinks
  AllowOverride None
  # Protect files and directories from prying eyes.
  <FilesMatch "\.(engine|inc|info|install|module|profile|po|schema|sh|.*sql|theme|tpl(\.php)?|xtmpl)$|^(code-style\.pl|Entries.*|Repository|Root|Tag|Template)$">
    Order allow,deny
  </FilesMatch>

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

  RewriteEngine On
  RewriteBase /
        <Files "cron.php">
    Order Deny,Allow
    Deny from all
    Allow from localhost
    Allow from 127.0.0.1
  </Files>
  # Rewrite URLs of the form 'index.php?q=x'.
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^(.*)$ index.php?q=$1 [L,QSA]
</Directory>
<VirtualHost *:<?php print $port; ?>>
  ServerName <?php print $hostname . PHP_EOL;  ?>
  DocumentRoot <?php print $docroot . PHP_EOL; ?>
  LogLevel warn
  ServerSignature Off
</VirtualHost>

