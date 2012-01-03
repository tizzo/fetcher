# Ignition #

This is the drush command to interact with sites managed by Ignition.

## Folder layout ##

Ignition creates a "working diredctory" with a specific layout for its uses.  That directory is laid 
out as follows:

- public_files - The drupal pubic files directory which will be symlinked to `sites/default/files` by default.  Generally group writable and group owned by the webserver.
- private_files - (Optional) A private file directory to be used by drupal.  Generally group writable and group owned by the webserver.
- logs - A folder for logs with files created for `mail.log`, `access.log` and `watchdog.log`.  Logs used in development, not recommended for production.
- code - A folder containing the actual checkout from the VCS repository.
- releases - (Optional) 
  - 1.0.1 - An example of a release folder for a tag called `1.0.1`.
- webroot - A symlink to the Drupal root (usually the root of `/code` in this directory).

## For Developers: ##

The ignition suite was designed to be easy to follow and to make as few 

### Site ###

This class has methods for all common top level operations though the details are delegated to
child objects.  This is the external most interface and acts as a gateway to the internal services
for performing most common operations (though the internal services are considered public APIs as
well).

### System ###

The system provides information and functionality specific to the operating system environment in use.

### Database ###

A Service class for administering a specific database.

### VCS ###

A handler class for the version control system.

### Server ###

A representation of the web server that will serve Drupal pages.  Server may be a bad idea as it is
different on different systems.  Perhaps this should be collapsed into System.
