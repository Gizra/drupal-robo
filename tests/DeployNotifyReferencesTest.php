<?php

namespace RoboComponents\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoboComponents\DeploymentTrait;
use RoboComponents\Tests\Fixtures\TestRoboFile;

/**
 * Unit tests for parsing issue/PR numbers out of merge commit messages.
 *
 * Covers the commit shapes GitHub produces, with no API round-trip.
 */
#[CoversMethod(DeploymentTrait::class, 'parseDeployNotifyReferences')]
class DeployNotifyReferencesTest extends TestCase {

  /**
   * Each commit message shape resolves to the expected issue and PR numbers.
   */
  #[DataProvider('commitMessageCases')]
  public function testParseDeployNotifyReferences(string $commit_message, array $expected_issues, ?int $expected_pr): void {
    $ref = new \ReflectionMethod(TestRoboFile::class, 'parseDeployNotifyReferences');
    $result = $ref->invoke(new TestRoboFile(), $commit_message);

    $this->assertSame($expected_issues, $result['issue_numbers']);
    $this->assertSame($expected_pr, $result['pr_number']);
  }

  /**
   * Commit message => [expected issue numbers, expected PR number].
   *
   * @return array<string, array{string, int[], int|null}>
   *   The test cases keyed by a human-readable label.
   */
  public static function commitMessageCases(): array {
    return [
      // The reported case: a squash merge with the "Issue #NNNN:" title, so
      // the issue resolves from the subject with no API round-trip.
      'squash merge with issue in title' => [
        'Issue #3487: Fix deploy:notify by exporting GITHUB_COMMIT_MESSAGE (#3490)',
        [3487],
        3490,
      ],
      // Same, with the trailing co-author line the real message carried.
      'squash merge with trailing co-author line' => [
        "Issue #3487: Fix deploy:notify (#3490)\nCo-authored-by: Claude <noreply@anthropic.com>",
        [3487],
        3490,
      ],
      // Classic merge commit: the branch is named after the issue number.
      'classic merge commit' => [
        'Merge pull request #10 from Gizra/1234',
        [1234],
        NULL,
      ],
      // Squash merge with no issue reference in the subject: only the PR number
      // is known, the issue has to come from the PR body later.
      'squash merge without issue in title' => [
        'Some unrelated title (#789)',
        [],
        789,
      ],
      // Nothing parseable at all.
      'no references' => [
        'Just a plain commit message',
        [],
        NULL,
      ],
    ];
  }

}
