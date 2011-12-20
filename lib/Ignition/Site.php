<?php

namespace Ignition;

class site {

  protected $system = FALSE;

  protected $database = FALSE;

  protected $vcs = FALSE;

  protected $server = FALSE;

  protected $siteInfo = array();

  protected $workingDirectory = '';

  protected $codeDirectory = '';

  protected $siteDBPassword = '';

  // TODO: Commit to this and use it?
  protected $production = TRUE;

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
    // Create the working directory if it does not already exist.
    $this->system->ensureFolderExists($this->workingDirectory);
    // Create a log directory if it does not already exist.
    $this->system->ensureFolderExists($this->workingDirectory . '/logs');
    // Create log files if they do not already exist.
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
  }

  public function checkout() {
    //$this->vcs->checkout();
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
