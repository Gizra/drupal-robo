<?php

namespace RoboComponents\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoboComponents\AutoUpdateTrait;
use RoboComponents\Tests\Fixtures\TestRoboFile;

/**
 * Unit tests for the Drupal-version to Composer-constraint derivation.
 *
 * Pure logic extracted from AutoUpdateTrait::updateModules() — no Drupal, no
 * external tools — which is exactly the kind of pocket worth covering directly.
 */
#[CoversMethod(AutoUpdateTrait::class, 'deriveComposerConstraint')]
class DeriveComposerConstraintTest extends TestCase {

  /**
   * Each Drupal version pattern maps to the expected Composer constraint.
   */
  #[DataProvider('constraintCases')]
  public function testDeriveComposerConstraint(string $recommended, string $constraint, ?string $major): void {
    $ref = new \ReflectionMethod(TestRoboFile::class, 'deriveComposerConstraint');
    $result = $ref->invoke(new TestRoboFile(), $recommended);

    $this->assertSame($constraint, $result['constraint']);
    $this->assertSame($major, $result['major']);
  }

  /**
   * Recommended version => [expected constraint, expected major].
   *
   * @return array<string, array{string, string, string|null}>
   *   The test cases keyed by a human-readable label.
   */
  public static function constraintCases(): array {
    return [
      'semver stable' => ['4.0.0', '4.0', '4'],
      'semver prerelease' => ['4.0.0-alpha6', '4.0@alpha', '4'],
      'drupal legacy stable' => ['8.x-4.0', '4.0', '4'],
      'drupal legacy prerelease' => ['8.x-4.0-alpha6', '4.0@alpha', '4'],
      'two-segment' => ['2.15', '2.15', '2'],
      'rc suffix' => ['3.0.0-rc8', '3.0@rc', '3'],
      'unparseable passes through' => ['dev-main', 'dev-main', NULL],
    ];
  }

}
