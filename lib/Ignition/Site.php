<?php

namespace Ignition;

class Site {

  /**
   * The system provider, a dependency injected into the constructor.
   */
  protected $system = FALSE;

  /**
   * The database provider, a dependency injected into the constructor.
   */
  protected $database = FALSE;

  /**
   * The vcs provider, a dependency injected into the constructor.
   */
  protected $vcs = FALSE;

  /**
   * The server provider, a dependency injected into the constructor.
   */
  protected $server = FALSE;


  /**
   * A stdClass object of whatever information is available about the site.
   */
  protected $siteInfo = array();

  /**
   * The path on disk of the site's containing folder.
   *
   * Usually immediately inside the server's webroot and containing all code and files for the site.
   */
  protected $workingDirectory = '';

  /**
   * The path within the working directory where we are placing code during this operation.
   *
   * For sites in development, this will usually be `code`.  For releases it will be a folder
   * 
   */
  protected $codeDirectory = '';

  /**
   * The path containing Drupal's index.php.
   */
  protected $drupalRoot = '';

  /**
   * A drush compatible $db_spec array as would be generaged by _drush_sql_get_db_spec().
   */
  protected $dbSpec = array();

  /**
   * Constructor function to allow dependency injection.
   */
  public function __construct($config) {
    foreach ($config as $name => $value) {
      if (isset($this->{$name})) {
        $this->{$name} = $value;
      }
    }
    // TODO: What if our construct method didn't receive all this data?
    // TODO: Should we be doing this here?
    if ($this->workingDirectory == '') {
      // TODO: Add optional webroot from siteInfo.
      //if (isset($this->siteInfo->
      $this->workingDirectory = $this->system->getWebRoot() . '/' . $this->siteInfo->name;
    }
    if ($this->codeDirectory == '') {
      // TODO: This needs to be smarter:
      $this->codeDirectory = $this->workingDirectory . '/' . 'code';
    }
    // Configure the vcs plugin.
    if ($this->vcs) {
      $config = array();
      $config['codeDirectory'] = $this->codeDirectory;
      if (isset($this->siteInfo->vcs_url)) {
        $config['vcsURL'] = $this->siteInfo->vcs_url;
      }
      $this->vcs->configure($config);
    }
    if ($this->database) {
      $db_spec = array();
      // TODO: make this in some way configurable?
      // If we have credentials, use them?
      if (count($this->dbSpec)) {
        $db_spec = $this->dbSpec;
      }
      $this->database->configure(array('db_spec' => $db_spec));
    }
  }

  /**
   * Create a new database
   */
  public function ensureDatabase() {
    if (!$this->database->exists()) {
      return $this->database->createDatabase();
    }
    return TRUE;
    if (!$this->database->userExists()) {
      $name = $this->siteInfo->name;
      $this->database->createUser($name, $this->siteDBPassword);
      $this->database->grantAccessToUser($name, $name);
    }
  }

  /**
   * Setup our basic working directory.
   */
  public function setUpWorkingDirectory() {

    // Ensure we have our working directory.
    $this->system->ensureFolderExists($this->workingDirectory);

    // Ensure we have a log directory.
    $this->system->ensureFolderExists($this->workingDirectory . '/logs');

    // Ensure we have our log files.
    // TODO: We probably only want these on dev.
    $this->system->ensureFileExists($this->workingDirectory . '/logs/access.log');
    $this->system->ensureFileExists($this->workingDirectory . '/logs/mail.log');
    $this->system->ensureFileExists($this->workingDirectory . '/logs/watchdog.log');

    // Ensure we have our files folders.
    $this->system->ensureFolderExists($this->workingDirectory . '/public_files', NULL, $this->system->getWebUser());
    chmod($this->workingDirectory . '/public_files', 0775);
    if (isset($this->siteInfo->{'private files'})) {
      $this->system->ensureFolderExists($this->workingDirectory . '/private_files', NULL, $this->system->getWebUser());
      chmod($this->workingDirectory . '/public_files', 0775);
    }

    // TODO: Don't lie quite so much.
    return TRUE;
  }

  /**
   * Checks to see whether settings.php exists and creates it if it does not.
   */
  public function ensureSettingsFileExists() {
    // TODO: Remove this once we get the vcs checkout rockin' and rollin'.
    return TRUE;
    // TODO: Support multisite?
    $settings_file = $this->drupalRoot . '/sites/default/settings.php';
    if (!is_file($settings_file)) {
      // TODO: Get the settings.php for the appropriate version.
      $settings_php_contents = '';
      //$settings_php_contents = ignition_get_asset('settings.php', '');
      // Allow settings to be checked into versioncontrol and automatically included from settings.php.
      if (is_file($this->drupalRoot . '/sites/default/site-settings.php')) {
        $settings_php_contents .= "\r\n  require_once('site-settings.php');";
      }
      drush_ignition_write_file($settings_php_file_path, $settings_php_contents);
    }
  }


  /**
   *
   */
  public function checkout($branch = NULL) {
    // TODO: Populate vcs with the necessary info (remote URL & local URL).
    if (!is_dir($this->codeDirectory)) {
      $this->vcs->initialCheckout($branch);
    }
    else {
      // TODO: Switch to the right branch or something?
      // $this->vcs->update($this->siteInfo->vcsURL, $this->codeDirectory, $branch);
    }
    if (is_dir($this->codeDirectory . '/webroot')) {
      $this->drupalRoot = $this->codeDirectory . '/webroot';
    }
    else {
      $this->drupalRoot = $this->codeDirectory;
    }
  }

  /**
   * Ensure that all symlinks besides the webroot symlink have been created.
   */
  public function ensureSymLinks() {
    return $this->system->ensureSymLink($this->workingDirectory . '/public_files', $this->drupalRoot . '/sites/default/files');
  }

  public function updateCode() {
  }

  public function syncEnvDatabase() {
  }

  public function loadDatabaseBackup() {
  }

  /**
   * Removes all traces of this site from this system.
   */
  public function delete() {
    $this->system->ensureDeleted($this->workingDirectory);
    $this->system->removeSite($this->siteInfo->name);
  }

  /**
   * Get the code directory.
   *
   * @return string
   */
  public function getCodeDirectory() {
    return $this->codeDirectory;
  }

  /**
   * Get the working directory.
   *
   * @return string
   */
  public function getWorkingDirectory() {
    return $this->workingDirectory;
  }

  public function writeSiteInfoFile() {
    $siteInfo = $this->siteInfo;
    $string = json_encode($siteInfo);
    $this->system->writeFile($this->workingDirectory . '/site_info.json', $string);
  }
}
