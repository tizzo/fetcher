<?php

namespace Ignition;

class site {

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
      // TODO: Add optional webroot.
      //if (isset($this->siteInfo->
      $this->workingDirectory = $this->system->getWebRoot() . '/' . $this->siteInfo->name;
    }
    if ($this->codeDirectory == '') {
      // TODO: This needs to be smarter:
      $code_directory = $this->workingDirectory . '/' . 'code';
    }
  }

  /**
   * Create a new database
   */
  public function createNewDatabase() {
    $name = $this->siteInfo->name;
    $this->database->createDatabase($name);
    $this->siteDBPassword = drush_ignition_make_random_password();
    $this->database->createUser($name, $this->siteDBPassword);
    $this->database->grantAccessToUser($name, $name);
  }

  public function setUp() {

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
    if ($this->siteInfo->{'private files'}) {
      $this->system->ensureFolderExists($this->workingDirectory . '/private_files', NULL, $this->system->getWebUser());
      chmod($this->workingDirectory . '/public_files', 0775);
    }

    // TODO: Support multisite?
    $settings_file = $this->siteRoot . '/sites/default/settings.php';
    if (!is_file($settings_file)) {
      $settings_php_contents = '';
      // TODO: Get the settings.php for the appropriate version.
      //ignition_get_asset('settings.php', '');
      ignition_write_file($settings_file, $settings_php_contents);
    }

    // Create symlinks.
    $this->system->createSymlink($this->workingDirectory . '/public_files', $this->drupalRoot . '/sites/default/files');
  }

  public function checkout() {
    $this->vcs->checkout();
  }

  public function update() {
  }

  public function syncEnvDatabase() {
  }

  public function loadDatabaseBackup() {
  }

  public function delete() {
  }

}