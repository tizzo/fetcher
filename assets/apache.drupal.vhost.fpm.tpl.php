<Directory <?php print $docroot; ?> >
  Options -Indexes
  Options +FollowSymLinks
  AllowOverride All
  Require all granted

  # Not a part of Drupal's stock .htaccess but added as a measure of security.
  <FilesMatch "(^LICENSE|CHANGELOG|MAINTAINERS|INSTALL|UPGRADE|API|README).*\.txt$">
    Order deny,allow
    Deny from all
  </FilesMatch>

  <Files "cron.php">
    Order Deny,Allow
    Deny from all
    Allow from localhost
    Allow from 127.0.0.1
  </Files>
</Directory>
<VirtualHost *:<?php print $port; ?>>
  ServerName <?php print $hostname . PHP_EOL; ?>
  DocumentRoot <?php print $docroot . PHP_EOL; ?>
  <FilesMatch \.php$>
    SetHandler proxy:fcgi://<?php print $fpm_url . PHP_EOL; ?>
  </FilesMatch>
  LogLevel warn
  ServerSignature Off
</VirtualHost>
