<?php

namespace RoboComponents;

use Robo\ResultData;

/**
 * Compilation of theme source assets.
 */
trait ThemeTrait {

  /**
   * The theme sources whose changes trigger a rebuild, relative to the theme.
   *
   * @var string[]
   */
  protected static array $themeRebuildPaths = [
    'src',
    'package.json',
    'package-lock.json',
    'tailwind.config.js',
    'postcss.config.js',
  ];

  /**
   * Compile the theme.
   *
   * Skips the rebuild when no theme source changed in the last commit, saving
   * the npm install + build minutes on deploys that don't touch the theme.
   */
  public function themeCompile(): void {
    $theme_dir = $this->getThemeBasePath();

    if (!$this->themeNeedsRebuild($theme_dir)) {
      $this->say('Theme sources unchanged; skipping the rebuild.');
      return;
    }

    $directories = [
      'css',
      'fonts',
      'js',
      'images',
    ];

    // Cleanup and create directories.
    $this->_deleteDir($theme_dir . '/dist');
    foreach ($directories as $dir) {
      $directory = $theme_dir . '/dist/' . $dir;
      $this->_mkdir($directory);
    }

    // Make sure we have all the node packages.
    $this->_exec("cd $theme_dir && npm ci");

    // Compile all assets (CSS, JS, fonts, images) in parallel via npm scripts.
    $result = $this->_exec("cd $theme_dir && npm run build");

    if ($result->getExitCode() !== 0) {
      throw new \Exception('Theme compilation failed.');
    }

    $this->_exec('drush cache:rebuild');
  }

  /**
   * Whether the theme has to be recompiled.
   *
   * Combines the impure inputs (missing artifact, git diff) and defers the
   * decision to themeShouldRebuild() so the logic stays unit-testable.
   *
   * @param string $theme_dir
   *   The theme base path.
   *
   * @return bool
   *   TRUE when a rebuild is required.
   */
  protected function themeNeedsRebuild(string $theme_dir): bool {
    $dist_exists = is_dir($theme_dir . '/dist');

    $paths = [];
    foreach (self::$themeRebuildPaths as $path) {
      $paths[] = escapeshellarg($theme_dir . '/' . $path);
    }
    // Compare the last commit with its parent; the paths that are absent from
    // the tree are simply reported as unchanged by git.
    $result = $this->taskExec('git diff --name-only HEAD^ HEAD -- ' . implode(' ', $paths))
      ->printOutput(FALSE)
      ->run();

    return $this->themeShouldRebuild($dist_exists, $result->getExitCode() !== 0, $result->getMessage());
  }

  /**
   * Decides whether to rebuild from the gathered signals.
   *
   * @param bool $dist_exists
   *   Whether the compiled `dist/` artifact is present.
   * @param bool $diff_failed
   *   Whether the git diff could not be computed (e.g. shallow clone, root
   *   commit).
   * @param string $changed_files
   *   The newline-separated theme sources that changed in the last commit.
   *
   * @return bool
   *   TRUE when a rebuild is required.
   */
  protected function themeShouldRebuild(bool $dist_exists, bool $diff_failed, string $changed_files): bool {
    // Never ship a site with no compiled assets, and rebuild when we can't tell
    // what changed.
    if (!$dist_exists || $diff_failed) {
      return TRUE;
    }
    return trim($changed_files) !== '';
  }

  /**
   * Update the caniuse-lite browserslist db.
   *
   * Any changes made as a result of this command should be committed.
   *
   * @return \Robo\ResultData
   *   The result.
   */
  public function caniuseUpdatedb(): ResultData {
    return $this->_exec('cd ' . $this->getThemeBasePath() . ' && npx browserslist@latest --update-db');
  }

}
