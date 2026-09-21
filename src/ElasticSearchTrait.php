<?php

namespace RoboComponents;

/**
 * Provisions ElasticSearch indices, roles, users and analysis settings.
 *
 * Exposes `elasticsearch:provision`; override the protected getters to match a
 * project's index and site layout.
 */
trait ElasticSearchTrait {

  /**
   * The index base names, without the environment prefix or suffix.
   *
   * @return string[]
   *   Defaults to ['server'].
   */
  protected function elasticsearchIndices(): array {
    return ['server'];
  }

  /**
   * The site identifiers that scope ElasticSearch roles and users.
   *
   * @return string[]
   *   Defaults to ['server'].
   */
  protected function elasticsearchSites(): array {
    return ['server'];
  }

  /**
   * The environments that each get their own set of indices.
   *
   * @return string[]
   *   Defaults to ['qa', 'dev', 'test', 'live'].
   */
  protected function elasticsearchEnvironments(): array {
    return ['qa', 'dev', 'test', 'live'];
  }

  /**
   * The directory holding stopword .txt files, relative to the project root.
   *
   * @return string
   *   Defaults to 'config/elasticsearch/stopwords'.
   */
  protected function elasticsearchStopwordsPath(): string {
    return 'config/elasticsearch/stopwords';
  }

  /**
   * The synonym file path, relative to the project root.
   *
   * @return string
   *   Defaults to 'config/elasticsearch/synonyms.txt'.
   */
  protected function elasticsearchSynonymsPath(): string {
    return 'config/elasticsearch/synonyms.txt';
  }

  /**
   * The index name prefix for the given ElasticSearch URL.
   *
   * DDEV's single-node ElasticSearch uses the database prefix; hosted clusters
   * use the Pantheon prefix.
   *
   * @param string $es_url
   *   Fully qualified URL to ElasticSearch.
   *
   * @return string
   *   The index name prefix.
   */
  protected function elasticsearchIndexPrefix(string $es_url): string {
    return strstr($es_url, '//elasticsearch:') !== FALSE
      ? 'elasticsearch_index_db_'
      : 'elasticsearch_index_pantheon_';
  }

  /**
   * The environments the provisioning run currently targets.
   *
   * NULL until a run narrows it, so the analysis commands default to every
   * configured environment.
   *
   * @var string[]|null
   */
  private ?array $elasticsearchActiveEnvironments = NULL;

  /**
   * Provision indices, roles, users and analysis settings.
   *
   * @param string $es_url
   *   Fully qualified URL to ElasticSearch, e.g. http://elasticsearch:9200 .
   * @param string $username
   *   The username of the ElasticSearch admin user.
   * @param string $password
   *   The password of the ElasticSearch admin user.
   * @param string|null $environment
   *   Limit provisioning to a single environment, to test index config changes.
   * @param bool $needs_users_override
   *   Force writing the credentials file even when users already exist.
   *
   * @throws \Exception
   */
  public function elasticsearchProvision(string $es_url, string $username, string $password, ?string $environment = NULL, bool $needs_users_override = FALSE): void {
    $needs_users = TRUE;

    $es_url = rtrim($es_url, '/');
    $index_prefix = $this->elasticsearchIndexPrefix($es_url);
    if (strstr($es_url, '//elasticsearch:') !== FALSE) {
      // Local DDEV cluster: no security, so no roles or users to create.
      $needs_users = FALSE;
    }
    else {
      $result = json_decode($this
        ->taskExec("curl -u $username:$password $es_url/_security/user")
        ->printOutput(FALSE)
        ->run()
        ->getMessage(), TRUE);
      if (!is_array($result) || isset($result['error'])) {
        throw new \Exception('Cannot connect to ES or security not enabled');
      }
      foreach (array_keys($result) as $existing_username) {
        foreach ($this->elasticsearchSites() as $site) {
          if (strstr((string) $existing_username, $site) !== FALSE) {
            // A user already exists for this site, so keep the existing ones.
            $needs_users = FALSE;
            break 2;
          }
        }
      }
    }

    $index_creation = $this->taskParallelExec();
    $role_creation = $this->taskParallelExec();
    $user_creation = $this->taskParallelExec();
    $credentials = [];

    $environments = !empty($environment) ? [$environment] : $this->elasticsearchEnvironments();
    // Remember the targeted environments so the analysis commands below stay in
    // scope when a single environment was requested.
    $this->elasticsearchActiveEnvironments = $environments;

    foreach ($environments as $environment) {
      $environment = str_replace('-', '_', $environment);
      foreach ($this->elasticsearchIndices() as $index) {
        $index_creation->process("curl -u $username:$password -X PUT $es_url/{$index_prefix}{$index}_$environment");
      }
      foreach ($this->elasticsearchSites() as $site) {
        $allowed_indices = [];
        foreach ($this->elasticsearchIndices() as $index) {
          if (strstr($index, $site) !== FALSE) {
            $allowed_indices[] = '"' . $index_prefix . $index . '_' . $environment . '"';
          }
        }
        $allowed_indices = implode(',', $allowed_indices);

        $role_data = <<<END
{ "cluster": ["all"],
  "indices": [
    {
      "names": [ $allowed_indices ],
      "privileges": ["all"]
    }
  ]
}
END;

        $role_creation->process("curl -u $username:$password -X POST $es_url/_security/role/{$site}_$environment -H 'Content-Type: application/json' --data '$role_data'");

        // Generate a random password or re-use an existing one from the JSON.
        $existing_password = $this->getElasticsearchUserPassword($site, $environment);
        $user_pw = !empty($existing_password) ? $existing_password : $this->randomStr();
        $user_data = <<<END
{ "password" : "$user_pw",
  "roles": [ "{$site}_$environment" ]
}
END;
        $credentials[$site][$environment] = $user_pw;
        $user_creation->process("curl -u $username:$password -X POST $es_url/_security/user/{$site}_$environment -H 'Content-Type: application/json' --data '$user_data'");
      }
    }

    $index_creation->run();
    if ($needs_users || $needs_users_override) {
      $role_creation->run();
      $user_creation->run();

      // Expose the credentials as files. Handle them securely and delete them
      // after the execution.
      foreach ($credentials as $site => $credential_per_environment) {
        file_put_contents($site . '.es.secrets.json', json_encode($credential_per_environment));
      }
    }

    $this->elasticsearchStopwords($es_url, $username, $password);
    $this->elasticsearchSynonyms($es_url, $username, $password);
    $this->elasticsearchAnalyzer($es_url, $username, $password);
  }

  /**
   * Apply the default analyzer to every targeted index.
   *
   * @param string $es_url
   *   Fully qualified URL to ElasticSearch, e.g. http://elasticsearch:9200 .
   * @param string $username
   *   The username of the ElasticSearch admin user.
   * @param string $password
   *   The password of the ElasticSearch admin user.
   *
   * @throws \Exception
   */
  public function elasticsearchAnalyzer(string $es_url, string $username = '', string $password = ''): void {
    $analyzer_data = <<<END
{
  "analysis": {
    "analyzer": {
      "default": {
        "type": "custom",
        "char_filter":  [ "html_strip" ],
        "tokenizer": "standard",
        "filter": [ "lowercase", "stop", "synonym" ]
      }
    }
  }
}
END;

    $this->applyIndexSettings($es_url, $username, $password, $analyzer_data);
  }

  /**
   * Apply the stopword filter to every targeted index.
   *
   * @param string $es_url
   *   Fully qualified URL to ElasticSearch, e.g. http://elasticsearch:9200 .
   * @param string $username
   *   The username of the ElasticSearch admin user.
   * @param string $password
   *   The password of the ElasticSearch admin user.
   * @param string|null $stopwords
   *   Directory of stopword .txt files. Defaults to
   *   elasticsearchStopwordsPath().
   *
   * @throws \Exception
   */
  public function elasticsearchStopwords(string $es_url, string $username = '', string $password = '', ?string $stopwords = NULL): void {
    $stopwords = $stopwords ?? $this->elasticsearchStopwordsPath();
    $stopwords_combined = [];
    $stopwords_files = scandir($stopwords) ?: [];
    foreach ($stopwords_files as $stopwords_file) {
      // Only read the .txt stopword lists.
      if (!str_ends_with($stopwords_file, '.txt')) {
        continue;
      }
      $stopwords_combined = array_merge($stopwords_combined, file($stopwords . '/' . $stopwords_file) ?: []);
    }

    if (empty($stopwords_combined)) {
      throw new \Exception("The stopword lists would be empty, check the specified files");
    }
    $prepared_stopwords = [];
    foreach ($stopwords_combined as $stopword) {
      if (empty(trim($stopword))) {
        continue;
      }
      $prepared_stopwords[] = '"' . trim($stopword) . '"';
    }
    $stopword_list = implode(',', $prepared_stopwords);
    $stopword_data = <<<END
{
  "analysis": {
    "filter": {
      "stop": {
        "type": "stop",
        "stopwords": [ $stopword_list ]
      }
    }
  }
}
END;

    $this->applyIndexSettings($es_url, $username, $password, $stopword_data);
  }

  /**
   * Apply the synonym filter to every targeted index.
   *
   * @param string $es_url
   *   Fully qualified URL to ElasticSearch, e.g. http://elasticsearch:9200 .
   * @param string $username
   *   The username of the ElasticSearch admin user.
   * @param string $password
   *   The password of the ElasticSearch admin user.
   * @param string|null $synonym_path
   *   The synonym list file. Defaults to elasticsearchSynonymsPath().
   *
   * @throws \Exception
   */
  public function elasticsearchSynonyms(string $es_url, string $username = '', string $password = '', ?string $synonym_path = NULL): void {
    $synonym_path = $synonym_path ?? $this->elasticsearchSynonymsPath();
    if (!file_exists($synonym_path)) {
      throw new \Exception("The synonym file does not exist");
    }
    $synonyms = file($synonym_path) ?: [];
    if (empty($synonyms)) {
      throw new \Exception("The synonym lists would be empty, check the specified files");
    }
    $prepared_synonyms = [];
    foreach ($synonyms as $synonym) {
      if (empty(trim($synonym))) {
        continue;
      }
      $prepared_synonyms[] = '"' . trim($synonym) . '"';
    }
    $synonym_list = implode(',', $prepared_synonyms);
    $synonym_data = <<<END
{
  "analysis": {
    "filter": {
      "synonym": {
        "type": "synonym_graph",
        "lenient": true,
        "synonyms": [ $synonym_list ]
      }
    }
  }
}
END;

    $this->applyIndexSettings($es_url, $username, $password, $synonym_data);
  }

  /**
   * Apply an index settings snippet to every targeted index.
   *
   * Settings changes require the index to be closed, so each index is closed,
   * updated and re-opened in turn.
   *
   * @param string $es_url
   *   Fully qualified URL to ElasticSearch.
   * @param string $username
   *   The username of the ElasticSearch admin user.
   * @param string $password
   *   The password of the ElasticSearch admin user.
   * @param string $data
   *   The JSON settings snippet to apply.
   */
  private function applyIndexSettings(string $es_url, string $username, string $password, string $data): void {
    $es_url = rtrim($es_url, '/');
    $index_prefix = $this->elasticsearchIndexPrefix($es_url);
    $environments = $this->elasticsearchActiveEnvironments ?? $this->elasticsearchEnvironments();
    foreach ($environments as $environment) {
      $environment = str_replace('-', '_', $environment);
      foreach ($this->elasticsearchIndices() as $index) {
        $this->taskExec("curl -u $username:$password -X POST $es_url/{$index_prefix}{$index}_$environment/_close")
          ->run();
        $this->taskExec("curl -u $username:$password -X PUT $es_url/{$index_prefix}{$index}_$environment/_settings -H 'Content-Type: application/json' --data '$data'")
          ->run();
        $this->taskExec("curl -u $username:$password -X POST $es_url/{$index_prefix}{$index}_$environment/_open")
          ->run();
      }
    }
  }

  /**
   * Generate a cryptographically secure random string for a password.
   *
   * @param int $length
   *   Length of the random string.
   * @param string $keyspace
   *   The set of characters the output string may contain.
   *
   * @return string
   *   The random string.
   *
   * @throws \Exception
   */
  protected function randomStr(
    int $length = 64,
    string $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
  ): string {
    if ($length < 1) {
      throw new \RangeException("Length must be a positive integer");
    }
    $pieces = [];
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
      $pieces[] = $keyspace[random_int(0, $max)];
    }
    return implode('', $pieces);
  }

  /**
   * The stored password for the given site and environment, if any.
   *
   * @param string $site
   *   The site ID.
   * @param string $environment
   *   The environment ID.
   *
   * @return string|null
   *   The password, or NULL when no credentials file entry exists.
   */
  protected function getElasticsearchUserPassword(string $site, string $environment): ?string {
    $credentials_file = $site . '.es.secrets.json';
    if (!file_exists($credentials_file)) {
      return NULL;
    }
    $credentials = file_get_contents($credentials_file);
    if (empty($credentials)) {
      return NULL;
    }
    $credentials = json_decode($credentials, TRUE);
    if (!is_array($credentials)) {
      return NULL;
    }
    if (!isset($credentials[$environment])) {
      return NULL;
    }
    return $credentials[$environment];
  }

}
