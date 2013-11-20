<Directory /var/www/<?php print $site_name; ?>/webroot/>
  Options FollowSymLinks
  AllowOverride None
  # Protect files and directories from prying eyes.
  <FilesMatch "\.(engine|inc|info|install|module|profile|po|schema|sh|.*sql|theme|tpl(\.php)?|xtmpl)$|^(code-style\.pl|Entries.*|Repository|Root|Tag|Template)$">
    Order allow,deny
  </FilesMatch>
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
<VirtualHost *:<?php print $port ?>>
  ServerName <?php print $hostname . PHP_EOL; ?>
  DocumentRoot <?php print $docroot . PHP_EOL; ?>
  LogLevel warn
  ServerSignature Off
</VirtualHost>

