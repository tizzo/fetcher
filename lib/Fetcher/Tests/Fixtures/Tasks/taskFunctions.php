<?php

/**
 * A not annotated function.
 */
function fetcher_test_non_annotated_function() {
  print 'FOOOO~' . PHP_EOL;
}

/**
 * An annotated fetcher task for testing.
 *
 * Adds an attiribute by the name of this function for testing.
 *
 * @fetcherTask some_function
 * @description This does some stuff
 * @afterMessage The stuff it does is awesome.
 */
function fetcher_task_annotated_function(\Fetcher\SiteInterface $site) {
  $site['fetcher_task_annotated_function'] = TRUE;
}
