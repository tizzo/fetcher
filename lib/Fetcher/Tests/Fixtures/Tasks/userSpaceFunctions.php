<?php

/**
 * @fetcherTask defined_in_file
 */
function fetcher_test_task_in_global_user_space(\Fetcher\SiteInterface $site) {
  $site['user_space_task_ran'] = TRUE; 
}
