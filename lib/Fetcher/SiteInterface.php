<?php

namespace Fetcher;

interface SiteInterface {

  /**
   * Apply an array of configuration.
   *
   * This array is set on the object using array access.
   * See the Pimple docs for details.
   *
   * @param $conf
   *  An array of keys and values to be handed to the site object.
   */
  public function configure(Array $conf);

  /**
   * Ensure the database exists, the user exists, and the user can connect.
   */
  public function ensureDatabase();

  /**
   * Build the drush alias and place it in the home folder.
   */
  public function ensureDrushAlias();

  /**
   * Setup our basic working directory.
   */
  public function ensureWorkingDirectory();

  /**
   * Ensure the site folder exists.
   */
  public function ensureSiteFolder();

  /**
   * Checks to see whether settings.php exists and creates it if it does not.
   */
  public function ensureSettingsFileExists();

  /**
   * Ensure the code is in place.
   */
  public function ensureCode();

  /**
   * Ensure that all symlinks besides the webroot symlink have been created.
   */
  public function ensureSymLinks();

  /**
   * Ensure the site has been added to the appropriate server (e.g. apache vhost).
   */
  public function ensureSiteEnabled();

  /**
   * Synchronize the database with a remote environment.
   */
  public function syncDatabase();

  /**
   * Synchronize the files with a remote environment.
   *
   * @param $type
   *   Public of private files.
   */
  public function syncFiles($type);

  /**
   * Removes The working diretory from this system.
   *
   * @fetcherTask remove_working_directory
   * @description Remove the working directory.
   * @afterMessage Removed `[[site.working_directory]]`.
   */
  public function removeWorkingDirectory($site = NULL);

  /**
   * Removes drush aliases for this site from this system.
   *
   * @fetcherTask remove_drush_aliases
   * @description Remove the site's drush aliases.
   * @afterMessage Removed `[[drush_alias.path]]`.
   */
  public function removeDrushAliases($site = NULL);

  /**
   * Removes the site's database and user.
   *
   * @fetcherTask remove_database
   * @description Remove the site's database and user.
   * @afterMessage Removed database `[[database.database]]` and user `[[database.user.database]]@[[database.user.hostname]]`.
   */
  public function removeDatabase($site = NULL);

  /**
   * Removes the site's virtualhost.
   *
   * @fetcherTask remove_vhost
   * @description Remove the site's virtualhost (or server equivalent).
   * @afterMessage Removed virtual host for `[[hostname]]`.
   */
  public function removeVirtualHost($site = NULL);

  /**
   * Register a build hook that can be run before or after a site build.
   *
   * @param $operation
   *   The operation upon which this hook will fire.
   *    'initial' - TODO: Document this.
   *    'before' - TODO: Document this.
   *    'after' - TODO: Document this.
   * @param $action
   *   This can be either a string to be executed at the command line or a
   *   Closure that accepts the site object as an argument.
   * @param $directory
   *   (Optional) Each hook is executed from inside the drupal code root.  If
   *   necessary a directory can be specified to run the command from.  This can
   *   either be a path relative to the Drupal root or an absolute path.
   */
  public function registerBuildHook($operation, $action, $directory = NULL);

  /**
   * Get the list of build hooks for this operation.
   */
  public function getOperationBuildHooks($operation);

  /**
   * Run all registered callbacks for an operation.
   */
  public function runOperationBuildHooks($operation);

  /**
   * Write a site info file from our siteInfo if it doesn't already exist.
   */
  public function ensureSiteInfoFileExists();

  /**
   * Parse site info from a string.
   */
  static public function parseSiteInfo($string);

  /**
   * Populate this object with defaults.
   */
  public function setDefaults();

}
