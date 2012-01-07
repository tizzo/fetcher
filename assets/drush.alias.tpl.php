<?php print '<?php'; ?>

$aliases['local'] = array(
  'root' => '<?php print $local_root; ?>',
);

$aliases['live'] = array(
<?php /* TODO: Iterate over environments:ta. */ ?>
  'root' => '<?php print $remote_root; ?>',
  'remote-host' => '<?php print $hostname; ?>',
<?php /* TODO: Add this to the service and make it dynamic. */ ?>
  'remote-user' => 'webadmin',
);
