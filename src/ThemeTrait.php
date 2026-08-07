<?php

namespace RoboComponents;

use Robo\ResultData;

/**
 * Compilation of theme source assets.
 */
trait ThemeTrait {

  /**
   * Compile the theme.
   */
  public function themeCompile(): void {
    $theme_dir = $this->getThemeBasePath();
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
