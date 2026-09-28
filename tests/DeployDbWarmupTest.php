<?php

namespace RoboComponents\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use RoboComponents\DeploymentTrait;
use RoboComponents\Tests\Fixtures\TestRoboFile;

/**
 * Unit tests for the post-deploy DB warm-up back-off and error detection.
 */
#[CoversMethod(DeploymentTrait::class, 'deployDbBackoffSeconds')]
#[CoversMethod(DeploymentTrait::class, 'deployIsColdDbError')]
class DeployDbWarmupTest extends TestCase {

  /**
   * The back-off grows 5 → 10 → 20 and then holds at the ceiling.
   */
  public function testBackoffSchedule(): void {
    $this->assertSame([5, 10, 20, 20, 20], [
      $this->backoff(0),
      $this->backoff(1),
      $this->backoff(2),
      $this->backoff(3),
      $this->backoff(4),
    ]);
  }

  /**
   * The cold-DB proxy timeout signature is recognised.
   */
  public function testColdDbErrorDetected(): void {
    $output = 'SQLSTATE[HY000]: General error: 9001 Max connect timeout reached while reaching hostgroup 1 after 10000ms';
    $this->assertTrue($this->isColdDbError($output));
  }

  /**
   * An unrelated failure is not mistaken for the cold-DB timeout.
   */
  public function testUnrelatedErrorNotDetected(): void {
    $this->assertFalse($this->isColdDbError('SQLSTATE[42S02]: Base table or view not found'));
  }

  /**
   * Invoke deployDbBackoffSeconds().
   *
   * @param int $attempt
   *   The zero-based attempt.
   *
   * @return int
   *   The back-off in seconds.
   */
  private function backoff(int $attempt): int {
    $method = new \ReflectionMethod(TestRoboFile::class, 'deployDbBackoffSeconds');
    return $method->invoke(new TestRoboFile(), $attempt);
  }

  /**
   * Invoke deployIsColdDbError().
   *
   * @param string $output
   *   The command output.
   *
   * @return bool
   *   Whether it matches the cold-DB signature.
   */
  private function isColdDbError(string $output): bool {
    $method = new \ReflectionMethod(TestRoboFile::class, 'deployIsColdDbError');
    return $method->invoke(new TestRoboFile(), $output);
  }

}
