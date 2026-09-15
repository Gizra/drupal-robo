<?php

namespace RoboComponents;

use Symfony\Component\Yaml\Yaml;

/**
 * Detects whether a deploy needs a Search API reindex.
 *
 * Reindexing the whole site on every deploy is slow and usually unnecessary,
 * yet skipping a needed reindex silently serves stale results. This trait
 * decides from the deployed commit alone whether the indexed data could have
 * changed, so a deploy can reindex only when it matters.
 *
 * It is backend-agnostic: detection inspects Search API's configuration layer
 * (index, server, processors, facets and the index's config dependencies),
 * which is the same regardless of the backend module — Solr, Elasticsearch,
 * database, and so on. The caller runs its own reindex commands (typically
 * `drush search-api:reindex` / `search-api:index`) when
 * searchApiReindexRequired() returns TRUE.
 */
trait SearchApiReindexTrait {

  /**
   * Whether the last deployed commit warrants a Search API reindex.
   *
   * Deploys are merge commits, so the changed files come from the merge's
   * first-parent diff. When that set cannot be read, returns TRUE: a redundant
   * reindex is cheaper than silently serving stale results.
   *
   * @return bool
   *   TRUE if any changed file could affect the indexed data.
   */
  protected function searchApiReindexRequired(): bool {
    // Deploys are merge commits; diff against the merge's first parent.
    $result = $this->taskExec('git show --first-parent HEAD --name-only --pretty=format:')
      ->printOutput(FALSE)
      ->run();

    if ($result->getExitCode() !== 0) {
      // We could not determine what changed. Reindex to stay on the safe side.
      $this->say('Could not read the commit file list; reindexing to be safe.');
      return TRUE;
    }

    $changed_files = array_filter(array_map('trim', explode("\n", $result->getMessage())));
    if (empty($changed_files)) {
      return FALSE;
    }

    $patterns = $this->searchApiReindexConfigPatterns();
    $index_dependencies = $this->searchApiIndexConfigDependencies();

    foreach ($changed_files as $file) {
      // Test files never affect the live index. A test for a Search API plugin
      // lives under a path like tests/src/.../Plugin/search_api/, which would
      // otherwise match the patterns below and trigger a needless reindex.
      if (preg_match('#(^|/)tests/#', $file) || str_ends_with($file, 'Test.php')) {
        continue;
      }

      foreach ($patterns as $pattern) {
        if (preg_match($pattern, $file)) {
          return TRUE;
        }
      }

      // Map a changed config file back to its config entity name and check
      // whether an index depends on it.
      if ($index_dependencies && preg_match('#(^|/)config/sync/(.+)\.yml$#', $file, $matches) && in_array($matches[2], $index_dependencies, TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Path patterns whose change implies the search index must be rebuilt.
   *
   * These cover backend-agnostic Search API configuration. The
   * `search_api[._]` prefix matches core Search API config
   * (`search_api.index.*`, `search_api.server.*`) as well as backend submodule
   * config (`search_api_solr.*`, `search_api_elasticsearch.*`, ...), whose
   * analyzers and field types change how documents are indexed. Override to
   * add project-specific paths.
   *
   * @return string[]
   *   PCRE patterns matched against each changed file path.
   */
  protected function searchApiReindexConfigPatterns(): array {
    return [
      // Search API index/server/settings and backend submodule config.
      '#(^|/)config/sync/search_api[._]#',
      // View displays that build the indexed document, e.g.
      // core.entity_view_display.node.article.search_index.yml. Presentation-
      // only "search_result" displays are excluded on purpose.
      '#(^|/)config/sync/.+\.search_index\.yml$#',
      // Custom Search API plugins (processors, data types, ...).
      '#(^|/)Plugin/search_api/#',
      // Facet config. A facet-only change rarely needs a reindex, but it is a
      // precise, rare signal, so reindex to be safe.
      '#(^|/)config/sync/facets(_summary)?\.#',
    ];
  }

  /**
   * Config entity names every Search API index depends on.
   *
   * A change to any of these — a field's storage definition, the indexing view
   * mode, the server, ... — can alter the indexed data even when no
   * `search_api.*` file itself changed.
   *
   * @return string[]
   *   Config entity names, e.g. "field.storage.node.field_title". Empty when
   *   none can be read.
   */
  protected function searchApiIndexConfigDependencies(): array {
    $dependencies = [];
    foreach (glob('config/sync/search_api.index.*.yml') ?: [] as $index_file) {
      try {
        $config = Yaml::parseFile($index_file);
      }
      catch (\Exception $e) {
        continue;
      }
      $dependencies = array_merge($dependencies, $config['dependencies']['config'] ?? []);
    }
    return array_values(array_unique($dependencies));
  }

}
