<?php

/**
 * Fetcher allows build hooks to be registered and these hooks will be run
 * immediately after the site code has been fetched in the order that they were
 * registered.  A file named fetcher.make.php should be placed in the site
 * folder of the site for which it should be
 * run
 *
 * These hooks will always be run when performing featches or updates of a site
 * and so they should be rerunable.
 */

// This registers a closure to be run after the build process.
// The closure accepts an \Fetcher\Site object as context and
// so it has access to all of the site's data, methods and component
// handlers and it has a chance to alter much behavior for any subsequent
// step.
//
// In the event of an error the closure should throw a descriptive
// excpetion.
//
// The closure should respect the site object's verbose and simulate
// values.
$site->registerBuildHook('after', function($site) {
  if ($site['verbose']) {
    drush_log('I am about to do some stuff that should be fine.', 'info');
  }
  if (!$site['simulate']) {
    drush_log('I think we\'re really about all done here.', 'ok');
  }
});

// This is a short hand approach where a string can be passed in
// instead of a closure. The regsitered command will be run as if
// executed at the terminalto.  If the terminal command exits with
// a non-zero status an exception will be thrown.
$site->registerBuildHook('after', 'echo "foo"', 'sites/all/themes/foo');

