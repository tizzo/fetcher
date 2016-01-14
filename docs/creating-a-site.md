# Creating a New Site with Fetcher

`drush fetcher-create` (`drush fec`) creates and installs a new site.

### Create a fresh Drupal 7 site 'foo'
    drush fec foo 7

### Create a fresh Drupal 8 site 'bar'
    drush fec bar 8
    
### Create a fresh site using an install profile
    drush fec baz --profile=panopoly-7.x-1.x
    
Use `drush help fec` to view all options to fetcher-create.

## What does fetcher-create do?

Fetcher-create:
- downloads the code
- creates an Apache VirtualHost
- creates a MySQL database and database user
- installs Drupal

If you are creating a site on your local machine, be sure to add an entry to your machine's `hosts` file to point your new site's domain (e.g. 'foo.local') to 127.0.0.1 (or the IP of your virtual machine).

## Folder layout ##

Fetcher creates a "working directory" with a specific layout for its uses.  That directory is laid 
out as follows:

  - public_files - The Drupal public files directory which will be symlinked to `sites/default/files` by default.  Generally group writable and group owned by the webserver.
  - private_files - (Optional) A private file directory to be used by drupal.  Generally group writable and group owned by the webserver.
  - logs - A folder for logs with files created for `mail.log`, `access.log` and `watchdog.log`.  Logs used in development, not recommended for production.
  - code - A folder containing the actual checkout from the VCS repository.
  - releases - (Optional).
    - 1.0.1 - An example of a release folder for a tag called `1.0.1`.
  - webroot - A symlink to the Drupal root (usually the root of `/code` in this directory).

The `settings.php` file will be dynamically generated appropriately to the version of Drupal. You can include a `site-settings.php` file in the
`sites/default` folder and it will be dynamically included from the settings.php file.  This is the prefered method of including PHP files.

