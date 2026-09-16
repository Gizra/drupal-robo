<?php

namespace RoboComponents;

/**
 * Per-project configuration consumed by the shared Robo traits.
 *
 * A project's RoboFile composes this trait and implements the three abstract
 * getters. The remaining getters ship with sensible defaults and only need to
 * be overridden when a project diverges from the Gizra Drupal conventions.
 */
trait ProjectConfigTrait {

  /**
   * The machine name of the custom theme.
   *
   * Defaults to the drupal-starter convention; override only if a project
   * renamed its custom theme.
   *
   * @return string
   *   Defaults to 'server_theme'.
   */
  protected function getThemeName(): string {
    return 'server_theme';
  }

  /**
   * The GitHub project slug the deployment/release commands operate on.
   *
   * @return string
   *   For example: 'Gizra/drupal-starter'.
   */
  abstract protected function getGithubProject(): string;

  /**
   * The languages installed on the site, used for translation management.
   *
   * Do not include 'en', 'und' or 'zxx'.
   *
   * @return string[]
   *   For example: ['ar', 'es'].
   */
  abstract protected function getInstalledLanguages(): array;

  /**
   * The path to the custom theme, relative to the project root.
   *
   * @return string
   *   Defaults to web/themes/custom/<theme name>.
   */
  protected function getThemeBasePath(): string {
    return 'web/themes/custom/' . $this->getThemeName();
  }

  /**
   * The name of the admin user (UID 1 is blocked by default).
   *
   * @return string
   *   Defaults to 'AdminOne'.
   */
  protected function getAdminUser(): string {
    return 'AdminOne';
  }

  /**
   * The directory holding the PO translation files, relative to project root.
   *
   * @return string
   *   Defaults to 'config/po_files'.
   */
  protected function getPoFilesPath(): string {
    return 'config/po_files';
  }

  /**
   * The files / directories to exclude from deployment.
   *
   * Theme build artifacts are derived from getThemeBasePath() so overriding the
   * theme name is enough for the excludes to follow.
   *
   * @return string[]
   *   The list of paths to exclude.
   */
  protected function getDeploySyncExcludes(): array {
    $theme_base = $this->getThemeBasePath();
    return [
      '.bootstrap-fast.php',
      '.ddev',
      '.editorconfig',
      '.git',
      '.idea',
      '.pantheon',
      '.phpunit.result.cache',
      'ci-scripts',
      'pantheon.upstream.yml',
      'phpstan.neon',
      'phpstan-rules',
      'phpunit.xml.dist',
      'README.md',
      'RoboFile.php',
      'robo-components',
      'server.es.secrets.json',
      'pantheon-key.enc',
      'pantheon-key',
      'web/.csslintrc',
      'web/.eslintignore',
      'web/.eslintrc.json',
      'web/example.gitignore',
      'web/INSTALL.txt',
      'web/modules/README.txt',
      'web/profiles/README.txt',
      'web/README.md',
      'web/README.txt',
      'web/sites/default',
      'web/sites/simpletest',
      'web/sites/README.txt',
      'web/themes/README.txt',
      $theme_base . '/src',
      $theme_base . '/node_modules',
      $theme_base . '/package.json',
      $theme_base . '/package-lock.json',
      $theme_base . '/tailwind.config.js',
      $theme_base . '/postcss.config.js',
      'node_modules',
      'mass_patch.sh',
      'package.json',
      'package-lock.json',
      'web/libraries/font-awesome/js-packages',
      'web/libraries/font-awesome/metadata',
      'web/libraries/select2/src',
      'recipes',
      'yarn.lock',
      'patches.txt',
    ];
  }

}
