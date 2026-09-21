<?php

namespace RoboComponents\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoboComponents\ProjectConfigTrait;
use RoboComponents\Tests\Fixtures\TestRoboFile;

/**
 * Proves the shared traits compose into a working RoboFile.
 *
 * This is the guard rail for the getter refactor: if a trait still referenced a
 * removed static property, or an abstract getter went unimplemented, this class
 * would fail to load and every assertion here would error.
 */
#[CoversClass(ProjectConfigTrait::class)]
class CompositionTest extends TestCase {

  /**
   * The commands each trait is expected to expose after composition.
   */
  private const EXPECTED_COMMANDS = [
    'updateModules',
    'themeCompile',
    'caniuseUpdatedb',
    'phpcs',
    'localeImport',
    'localeImportToConfig',
    'localeExportFromConfig',
    'securityCheckDdos',
    'elasticsearchProvision',
    'elasticsearchAnalyzer',
    'elasticsearchStopwords',
    'elasticsearchSynonyms',
  ];

  /**
   * The fixture RoboFile under test.
   */
  private TestRoboFile $roboFile;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->roboFile = new TestRoboFile();
  }

  /**
   * Every expected command survives composition.
   */
  public function testCommandsAreComposed(): void {
    foreach (self::EXPECTED_COMMANDS as $command) {
      $this->assertTrue(
        method_exists($this->roboFile, $command),
        "Expected command '$command' to be composed into the RoboFile.",
      );
    }
  }

  /**
   * The three required getters return the project-specific values.
   */
  public function testRequiredConfigGetters(): void {
    $this->assertSame('my_theme', $this->callProtected('getThemeName'));
    $this->assertSame('Example/example-project', $this->callProtected('getGithubProject'));
    $this->assertSame(['fr', 'de'], $this->callProtected('getInstalledLanguages'));
  }

  /**
   * The defaulted getters derive sensible values from the required ones.
   */
  public function testDefaultedConfigGetters(): void {
    $this->assertSame('web/themes/custom/my_theme', $this->callProtected('getThemeBasePath'));
    $this->assertSame('AdminOne', $this->callProtected('getAdminUser'));
    $this->assertSame('config/po_files', $this->callProtected('getPoFilesPath'));
  }

  /**
   * The deploy excludes follow the theme name through getThemeBasePath().
   */
  public function testDeploySyncExcludesTrackTheme(): void {
    $excludes = $this->callProtected('getDeploySyncExcludes');
    $this->assertContains('web/themes/custom/my_theme/node_modules', $excludes);
    $this->assertNotContains('web/themes/custom/server_theme/node_modules', $excludes);
  }

  /**
   * Invoke a protected method on the fixture via reflection.
   */
  private function callProtected(string $method): mixed {
    $ref = new \ReflectionMethod($this->roboFile, $method);
    return $ref->invoke($this->roboFile);
  }

}
