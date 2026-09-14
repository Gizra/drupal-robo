<?php

namespace RoboComponents;

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Boots Drupal alongside Robo so commands can use the Drupal API.
 *
 * A project's RoboFile composes this trait to get a constructor that boots
 * Drupal on demand. Paths are resolved from the current working directory,
 * which Robo sets to the project root (where RoboFile.php lives) — do not use
 * __DIR__ here, as that would resolve to this package's directory once the
 * trait is installed under vendor/.
 *
 * A project that needs its own constructor can skip composing __construct and
 * call bootDrupal() itself:
 * @code
 * public function __construct() {
 *   // project-specific setup ...
 *   $this->bootDrupal();
 * }
 * @endcode
 */
trait DrupalBootTrait {

  /**
   * Boots Drupal in addition to Robo.
   */
  public function __construct() {
    $this->bootDrupal();
  }

  /**
   * Boots a full Drupal kernel so commands can use the Drupal API.
   *
   * Safe to call when Drupal is not installed yet: it returns early, leaving
   * Robo-only commands usable. Boot failures are caught and reported rather
   * than aborting, since many commands do not need Drupal.
   */
  protected function bootDrupal(): void {
    if (!$this->isDrupalInstalled()) {
      return;
    }

    $project_root = getcwd();
    if ($project_root === FALSE) {
      $this->yell('Unable to determine the working directory; skipping Drupal boot.');
      return;
    }

    try {
      // web/autoload.php returns the Composer class loader Drupal needs.
      $autoloader = require $project_root . '/web/autoload.php';
      // Keep the loader available for any command that still expects it.
      $GLOBALS['drupal_autoloader'] = $autoloader;

      // Drupal extension paths are resolved relative to the web root.
      chdir($project_root . '/web');
      $request = Request::createFromGlobals();
      $kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
      $kernel->handle($request);
      chdir($project_root);
    }
    catch (\Exception $e) {
      // Do not fail: several commands do not need Drupal.
      $this->yell($e->getMessage());
    }
  }

  /**
   * Checks if Drupal is installed by verifying database tables exist.
   *
   * The settings.php file alone is unreliable — DDEV auto-generates it even
   * before site installation — so this opens the configured database and
   * probes a core table.
   *
   * @return bool
   *   TRUE when the site database is reachable and initialized.
   */
  protected function isDrupalInstalled(): bool {
    $project_root = getcwd();
    if ($project_root === FALSE) {
      return FALSE;
    }

    // Drupal's settings.php is written to run with $app_root and $site_path in
    // scope (Drupal defines them before including it), so define them here too.
    $app_root = $project_root . '/web';
    $site_path = 'sites/default';
    $settings_file = $app_root . '/' . $site_path . '/settings.php';
    if (!file_exists($settings_file)) {
      return FALSE;
    }

    try {
      // PHPStan can't infer that including settings.php populates $databases.
      /** @var array<string, mixed> $databases */
      $databases = [];
      // Include settings to pick up the (DDEV-injected) DB credentials.
      include $settings_file;
      if (empty($databases['default']['default'])) {
        return FALSE;
      }
      $db = $databases['default']['default'];
      $port = !empty($db['port']) ? ";port={$db['port']}" : '';
      $dsn = "{$db['driver']}:host={$db['host']}{$port};dbname={$db['database']}";
      $pdo = new \PDO($dsn, $db['username'], $db['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
      $pdo->query("SELECT 1 FROM key_value LIMIT 1");
      return TRUE;
    }
    catch (\Throwable $e) {
      return FALSE;
    }
  }

}
