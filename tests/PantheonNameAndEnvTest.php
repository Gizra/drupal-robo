<?php

namespace RoboComponents\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use RoboComponents\DeploymentTrait;
use RoboComponents\Tests\Fixtures\TestRoboFile;

/**
 * Unit tests for resolving the Pantheon site and environment.
 *
 * Resolved solely from the `DDEV_PANTHEON_SITE` / `DDEV_PANTHEON_ENVIRONMENT`
 * ddev provider variables.
 */
#[CoversMethod(DeploymentTrait::class, 'getPantheonNameAndEnv')]
class PantheonNameAndEnvTest extends TestCase {

  /**
   * The original env var values, restored after each test.
   *
   * @var array<string, string|false>
   */
  private array $originalEnv = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['DDEV_PANTHEON_SITE', 'DDEV_PANTHEON_ENVIRONMENT'] as $name) {
      $this->originalEnv[$name] = getenv($name);
      putenv($name);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->originalEnv as $name => $value) {
      $value === FALSE ? putenv($name) : putenv("$name=$value");
    }
    parent::tearDown();
  }

  /**
   * The ddev env vars are used when both are set.
   */
  public function testResolvesFromDdevEnvVars(): void {
    putenv('DDEV_PANTHEON_SITE=drupal-starter');
    putenv('DDEV_PANTHEON_ENVIRONMENT=qa');

    $this->assertSame(
      ['name' => 'drupal-starter', 'env' => 'qa'],
      $this->invoke(),
    );
  }

  /**
   * A partial env config (only the site set) raises the actionable error.
   *
   * It must not return a bogus name/env pair.
   */
  public function testPartialEnvVarsDoNotResolve(): void {
    putenv('DDEV_PANTHEON_SITE=drupal-starter');

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Pantheon site and environment not found');
    $this->invoke();
  }

  /**
   * Missing env vars yield an actionable error.
   */
  public function testNoConfigThrows(): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Pantheon site and environment not found');
    $this->invoke();
  }

  /**
   * Invoke the protected method under test.
   *
   * @return array
   *   The resolved name/env pair.
   */
  private function invoke(): array {
    $method = new \ReflectionMethod(TestRoboFile::class, 'getPantheonNameAndEnv');
    return $method->invoke(new TestRoboFile());
  }

}
