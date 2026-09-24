<?php

namespace RoboComponents\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use RoboComponents\Tests\Fixtures\TestRoboFile;
use RoboComponents\ThemeTrait;

/**
 * Unit tests for the theme rebuild decision.
 *
 * Skip only when the artifact exists and nothing changed; every uncertain
 * case rebuilds so a deploy can't ship missing assets.
 */
#[CoversMethod(ThemeTrait::class, 'themeShouldRebuild')]
class ThemeShouldRebuildTest extends TestCase {

  /**
   * A missing dist/ artifact always rebuilds, even with no source changes.
   */
  public function testMissingArtifactRebuilds(): void {
    $this->assertTrue($this->invoke(FALSE, FALSE, ''));
  }

  /**
   * A failed diff (shallow clone, root commit) rebuilds to be safe.
   */
  public function testDiffFailureRebuilds(): void {
    $this->assertTrue($this->invoke(TRUE, TRUE, ''));
  }

  /**
   * Changed theme sources rebuild.
   */
  public function testChangedSourcesRebuild(): void {
    $changed = "web/themes/custom/my_theme/src/index.css\n";
    $this->assertTrue($this->invoke(TRUE, FALSE, $changed));
  }

  /**
   * An existing artifact with no source changes skips the rebuild.
   */
  public function testUnchangedSourcesSkip(): void {
    $this->assertFalse($this->invoke(TRUE, FALSE, "\n  \n"));
  }

  /**
   * Invoke the protected method under test.
   *
   * @param bool $dist_exists
   *   Whether the compiled artifact is present.
   * @param bool $diff_failed
   *   Whether the git diff failed.
   * @param string $changed_files
   *   The changed theme sources.
   *
   * @return bool
   *   Whether a rebuild is required.
   */
  private function invoke(bool $dist_exists, bool $diff_failed, string $changed_files): bool {
    $method = new \ReflectionMethod(TestRoboFile::class, 'themeShouldRebuild');
    return $method->invoke(new TestRoboFile(), $dist_exists, $diff_failed, $changed_files);
  }

}
