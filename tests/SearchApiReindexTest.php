<?php

namespace RoboComponents\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoboComponents\SearchApiReindexTrait;
use RoboComponents\Tests\Fixtures\TestRoboFile;

/**
 * Unit tests for the changed-file to reindex decision.
 *
 * Pure path logic from SearchApiReindexTrait — no git, no filesystem —
 * deciding whether a deploy's changed files warrant a reindex.
 */
#[CoversMethod(SearchApiReindexTrait::class, 'changedFilesRequireReindex')]
#[CoversMethod(SearchApiReindexTrait::class, 'searchApiReindexConfigPatterns')]
class SearchApiReindexTest extends TestCase {

  /**
   * Each changed-file set maps to the expected reindex decision.
   *
   * @param bool $expected
   *   The reindex decision the file set should produce.
   * @param string[] $changed_files
   *   The deploy's changed file paths.
   * @param string[] $index_dependencies
   *   Config entity names the indexes depend on.
   */
  #[DataProvider('reindexCases')]
  public function testChangedFilesRequireReindex(bool $expected, array $changed_files, array $index_dependencies): void {
    $method = new \ReflectionMethod(TestRoboFile::class, 'changedFilesRequireReindex');
    $result = $method->invoke(new TestRoboFile(), $changed_files, $index_dependencies);

    $this->assertSame($expected, $result);
  }

  /**
   * Label => [expected decision, changed files, index dependencies].
   *
   * @return array<string, array{bool, string[], string[]}>
   *   The test cases keyed by a human-readable label.
   */
  public static function reindexCases(): array {
    $index_dependencies = [
      'field.storage.node.field_title',
      'core.entity_view_mode.node.search_index',
    ];

    return [
      // Anything touching Search API config or an indexed document reindexes.
      'search_api index config' => [TRUE, ['config/sync/search_api.index.default.yml'], []],
      'search_api server config' => [TRUE, ['config/sync/search_api.server.solr.yml'], []],
      'search_api submodule config' => [TRUE, ['config/sync/search_api_solr.solr_field_type.text.yml'], []],
      'indexed view display' => [TRUE, ['config/sync/core.entity_view_display.node.article.search_index.yml'], []],
      'custom search_api plugin' => [TRUE, ['web/modules/custom/foo/src/Plugin/search_api/processor/Boost.php'], []],
      'facet config' => [TRUE, ['config/sync/facets.facet.brand.yml'], []],
      'facet summary config' => [TRUE, ['config/sync/facets_summary.facets_summary.block.yml'], []],
      'index dependency changed' => [TRUE, ['config/sync/field.storage.node.field_title.yml'], $index_dependencies],
      'one relevant file among unrelated ones' => [TRUE, ['README.md', 'config/sync/search_api.index.default.yml'], []],

      // Everything else leaves the index untouched.
      'unrelated code' => [FALSE, ['web/modules/custom/foo/src/Controller/FooController.php'], []],
      'documentation and metadata only' => [FALSE, ['README.md', 'composer.json'], []],
      'presentation-only view display' => [
        FALSE,
        ['config/sync/core.entity_view_display.node.article.search_result.yml'],
        [],
      ],
      'plugin test under tests dir' => [
        FALSE,
        ['web/modules/custom/foo/tests/src/Unit/Plugin/search_api/BoostTest.php'],
        [],
      ],
      'plugin test file elsewhere' => [
        FALSE,
        ['web/modules/custom/foo/src/Plugin/search_api/processor/BoostTest.php'],
        [],
      ],
      'config that no index depends on' => [
        FALSE,
        ['config/sync/field.storage.node.field_body.yml'],
        $index_dependencies,
      ],
      'dependency match but no dependencies read' => [FALSE, ['config/sync/field.storage.node.field_title.yml'], []],
      'nothing changed' => [FALSE, [], $index_dependencies],
    ];
  }

}
