# Fetcher Drush#

[![Build Status](https://travis-ci.org/tizzo/fetcher.png?branch=7.x-1.x)](https://travis-ci.org/tizzo/fetcher)
[![Coverage Status](https://coveralls.io/repos/tizzo/fetcher/badge.png?branch=7.x-1.x)](https://coveralls.io/r/tizzo/fetcher?branch=7.x-1.x)

Fetcher Drush is a command line client to interact with your Fetcher site and to do interesting things with the data retrieved from there.

This is the drush command to interact with sites managed by Fetcher.

## Folder layout ##

Fetcher creates a "working directory" with a specific layout for its uses.  That directory is laid 
out as follows:

  - public_files - The drupal pubic files directory which will be symlinked to `sites/default/files` by default.  Generally group writable and group owned by the webserver.
  - private_files - (Optional) A private file directory to be used by drupal.  Generally group writable and group owned by the webserver.
  - logs - A folder for logs with files created for `mail.log`, `access.log` and `watchdog.log`.  Logs used in development, not recommended for production.
  - code - A folder containing the actual checkout from the VCS repository.
  - releases - (Optional).
    - 1.0.1 - An example of a release folder for a tag called `1.0.1`.
  - webroot - A symlink to the Drupal root (usually the root of `/code` in this directory).

It is also worth noting that while settings.php will be dynamically generated appropriately to the version of Drupal, if there is a `site-settings.php` file in the
`sites/default` folder it will dynamically be included from the settings.php file.  This is the prefered method of including PHP files.

