<?php

namespace RoboComponents;

use Symfony\Component\Yaml\Yaml;

/**
 * Detects whether a deploy needs a Search API reindex.
 *
 * Uses the deployed commit alone, reindexing only when indexed data could
 * have changed. Backend-agnostic; inspects Search API config.
 */
trait SearchApiReindexTrait {

  /**
   * Whether the deployed commit warrants a Search API reindex.
   *
   * Deploys are merge commits, so changed files come from the first-parent
   * diff; returns TRUE when it cannot be read.
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
      // Test files never affect the live index. A Search API plugin's test
      // lives under tests/src/.../Plugin/search_api/, which would otherwise
      // match the patterns below and trigger a needless reindex.
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
   * Path patterns whose change implies the index must be rebuilt.
   *
   * The `search_api[._]` prefix matches core and submodule config that affects
   * how documents are indexed. Override to add project paths.
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
   * Changing a field's storage, view mode or the server can alter indexed
   * data even when no `search_api.*` file changed.
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
