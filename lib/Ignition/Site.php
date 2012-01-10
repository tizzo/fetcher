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

  protected $container = FALSE;

  /**
   * Constructor function to allow dependency injection.
   *
   */
  public function __construct(\Pimple $container) {

    $this->container = $container;

    $this->database = $container['database'];
    
    $this->siteInfo = $container['site info'];

    $this->vcs = $container['vcs'];

    $this->server = $container['server'];

    $this->system = $container['system'];

    $this->workingDirectory = $container['site.working_directory'];

    $this->codeDirectory = $container['site.code_directory'];

  }

  /**
   * Create a new database
   */
  public function ensureDatabase() {
    if (!$this->database->exists()) {
      $this->database->createDatabase();
    }
    if (!$this->database->userExists()) {
      $name = $this->siteInfo->name;
      $this->database->createUser();
      $this->database->grantAccessToUser();
    }
  }

  /**
   *
   */
  public function ensureDrushAlias() {
    $drushPath = $this->system->getUserHomeFolder() . '/.drush';
    $this->system->ensureFolderExists($drushPath);
    $drushFilePath = $this->getDrushAliasPath();
    if (!is_file($drushFilePath)) {
      $vars = array(
        'local_root' => $this->drupalRoot,
        'remote_root' => $this->drupalRoot,
        'hostname' => $this->container['remote.url'],
      );
      $content = \drush_ignition_get_asset('drush.alias', $vars);
      $this->system->writeFile($drushFilePath, $content);
    }
  }

  /**
   * Setup our basic working directory.
   */
  public function ensureWorkingDirectory() {

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
    $this->system->ensureFolderExists($this->workingDirectory . '/public_files', NULL, $this->server->getWebUser());
    if (isset($this->siteInfo->{'private files'})) {
      $this->system->ensureFolderExists($this->workingDirectory . '/private_files', NULL, $this->server->getWebUser());
    }

  }

  /**
   * Checks to see whether settings.php exists and creates it if it does not.
   */
  public function ensureSettingsFileExists() {
    // TODO: Support multisite?
    // TODO: This is ugly, what we're doing with this container here...
    $settingsFilePath = $this->container['site.webroot'] = $this->drupalRoot . '/sites/default/settings.php';
    if (!is_file($settingsFilePath)) {
      $conf = $this->container;
      $vars = array();
      $vars =  array(
        'database' => $conf['database.database'],
        'hostname' => $conf['database.hostname'],
        'username' => $conf['database.username'],
        'password' => $conf['database.password'],
        'driver' => $conf['database.driver'],
      );
      // TODO: Get the settings.php for the appropriate version.
      $content = \drush_ignition_get_asset('drupal.' . $this->container['version'] . '.settings.php', $vars);
      // Allow settings to be checked into versioncontrol and automatically included from settings.php.
      if (is_file($this->drupalRoot . '/sites/default/site-settings.php')) {
        $content .= "\rrequire_once('site-settings.php');\r";
      }
      $this->system->writeFile($settingsFilePath, $content);
    }
  }


  /**
   *
   */
  public function ensureCode() {
    if (!is_dir($this->codeDirectory)) {
      $this->vcs->initialCheckout();
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
    $this->system->ensureSymLink($this->workingDirectory . '/public_files', $this->drupalRoot . '/sites/default/files');
    $this->system->ensureSymLink($this->drupalRoot, $this->workingDirectory . '/webroot');
  }

  /**
   *
   */
  public function ensureSiteEnabled() {
    $server = $this->server;
    if (!$server->siteEnabled()) {
      $server->ensureSiteConfigured();
      $server->ensureSiteEnabled();
      $server->restart();
    }
  }

  public function syncEnvDatabase() {
  }

  public function getDrushAliasPath() {
    return $this->system->getUserHomeFolder() . '/.drush/' . $this->siteInfo->name . '.aliases.drushrc.php';
  }

  /**
   * Removes all traces of this site from this system.
   */
  public function remove() {
    $this->system->ensureDeleted($this->workingDirectory);
    $this->system->ensureDeleted($this->getDrushAliasPath());
    //$this->system->removeSite($this->siteInfo->name);
    if ($this->database->exists()) {
      $this->database->removeDatabase();
    }
    if ($this->database->userExists()) {
      $this->database->removeUser();
    }
    $this->server->ensureSiteRemoved();
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

  /**
   *
   */
  public function ensureSiteInfoFileExists() {
    $conf = $this->container;
    $siteInfo = array(
      'name' => $conf['site.name'],
      'vcs_url' => $conf['vcs.url'],
      'remote' => array(
        'name' => $conf['remote.name'],
        'hostname' => $conf['remote.url'],
      ),
    );
    $string = json_encode($siteInfo);
    $this->system->writeFile($this->workingDirectory . '/site_info.json', $string);
  }
}
