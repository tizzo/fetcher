<?php
$aliases['foo'] = array(
  'array_value' => 'foo',
  'fetcher' => array(
    'object_child' => array(
      'objectValue' => 'bar',
      'objectArray' => array(
        'ding' => 'dong',
      ),
    ),
    'array_child' => array(
      'way_down' => 'here',
    ),
  ),
);
$aliases['local'] = array(
  'uri' => 'test.local',
  'root' => '/var/www/Test/webroot',
);