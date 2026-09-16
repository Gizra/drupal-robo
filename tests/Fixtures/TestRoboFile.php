<?php

namespace RoboComponents\Tests\Fixtures;

use Robo\Tasks;
use RoboComponents\AutoUpdateTrait;
use RoboComponents\DeploymentTrait;
use RoboComponents\DrupalBootTrait;
use RoboComponents\PantheonRemoteTrait;
use RoboComponents\PhpcsTrait;
use RoboComponents\ProjectConfigTrait;
use RoboComponents\ReleaseNotesTrait;
use RoboComponents\SearchApiReindexTrait;
use RoboComponents\SecurityTrait;
use RoboComponents\ThemeTrait;
use RoboComponents\TranslationManagement\ExportFromConfig;
use RoboComponents\TranslationManagement\ImportToConfig;
use RoboComponents\TranslationManagement\ImportToUi;

/**
 * A minimal RoboFile that composes every shared trait.
 *
 * Mirrors how a real project wires the package together, so the composition
 * itself (no missing abstract getter, no unresolved cross-trait call) is
 * exercised by simply loading this class.
 */
class TestRoboFile extends Tasks {

  use AutoUpdateTrait;
  use DeploymentTrait;
  use DrupalBootTrait;
  use ExportFromConfig;
  use ImportToConfig;
  use ImportToUi;
  use PantheonRemoteTrait;
  use PhpcsTrait;
  use ProjectConfigTrait;
  use ReleaseNotesTrait;
  use SearchApiReindexTrait;
  use SecurityTrait;
  use ThemeTrait;

  /**
   * {@inheritdoc}
   */
  protected function getThemeName(): string {
    return 'my_theme';
  }

  /**
   * {@inheritdoc}
   */
  protected function getGithubProject(): string {
    return 'Example/example-project';
  }

  /**
   * {@inheritdoc}
   */
  protected function getInstalledLanguages(): array {
    return ['fr', 'de'];
  }

}
