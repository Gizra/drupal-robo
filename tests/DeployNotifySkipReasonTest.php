<?php

namespace RoboComponents\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use RoboComponents\DeploymentTrait;
use RoboComponents\Tests\Fixtures\TestRoboFile;

/**
 * Unit tests for the deploy:notify skip diagnostics.
 *
 * A missing GITHUB_COMMIT_MESSAGE used to print an empty line and return,
 * indistinguishable from success. The reason must now name the variable.
 */
#[CoversMethod(DeploymentTrait::class, 'deployNotifySkipReason')]
class DeployNotifySkipReasonTest extends TestCase {

  /**
   * An unset variable (`false`) reports the variable by name.
   */
  public function testUnsetVariableIsNamed(): void {
    $this->assertSame(
      'deploy:notify skipped: GITHUB_COMMIT_MESSAGE is not set',
      $this->invoke(FALSE),
    );
  }

  /**
   * An empty or whitespace-only message reports the variable by name.
   */
  public function testEmptyMessageIsNamed(): void {
    $this->assertSame(
      'deploy:notify skipped: GITHUB_COMMIT_MESSAGE is not set',
      $this->invoke('   '),
    );
  }

  /**
   * A non-merge message is reported, not silently dropped.
   */
  public function testUnrelatedMessageExplainsWhy(): void {
    $reason = $this->invoke('Fix a typo');
    $this->assertNotNull($reason);
    $this->assertStringContainsString('GITHUB_COMMIT_MESSAGE', $reason);
    $this->assertStringContainsString('Fix a typo', $reason);
  }

  /**
   * A merged-PR message proceeds (no skip reason).
   */
  public function testMergeCommitProceeds(): void {
    $this->assertNull($this->invoke('Merge pull request #10 from Gizra/branch'));
  }

  /**
   * A squash-merge message proceeds (no skip reason).
   */
  public function testSquashCommitProceeds(): void {
    $this->assertNull($this->invoke('Resolve the thing (#12)'));
  }

  /**
   * Invoke the protected method under test.
   *
   * @param string|false $git_commit_message
   *   The raw commit message value.
   *
   * @return string|null
   *   The skip reason, or NULL to proceed.
   */
  private function invoke(string|false $git_commit_message): ?string {
    $method = new \ReflectionMethod(TestRoboFile::class, 'deployNotifySkipReason');
    return $method->invoke(new TestRoboFile(), $git_commit_message);
  }

}
