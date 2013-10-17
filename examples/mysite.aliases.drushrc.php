<?php
// The sitename is the name of the alias file (`mysite`, in this example ).
// Here `dev` is the remote environment (the environment.remote site configuration key).
$aliases['dev'] = array(
  // The URI of this environment.
  'uri' => 'dev.mysite.com',
  // The path to the site root for this environment.
  'root' => '/var/www/html/mysite/webroot',
  'fetcher' => array(
    'title' => 'My Site',
    'name'  => 'mysite',
    'version' => 7,
    'code_fetcher.class' => 'Fetcher\CodeFetcher\VCS\Git',
    'code_fetcher.config' => array(
      'branch' => 'master',
      'url'   => 'git@github.com:mycompany/mysite.git',
    ),
    'environments' => array(
      'dev' => array(),
    ),
  ),
);
