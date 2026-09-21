<?php

namespace RoboComponents\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoboComponents\ElasticSearchTrait;
use RoboComponents\Tests\Fixtures\TestRoboFile;

/**
 * Unit tests for the pure ElasticSearch provisioning helpers.
 *
 * The index-prefix selection, password generation and credentials lookup —
 * no ElasticSearch cluster or curl involved.
 */
#[CoversMethod(ElasticSearchTrait::class, 'elasticsearchIndexPrefix')]
#[CoversMethod(ElasticSearchTrait::class, 'randomStr')]
#[CoversMethod(ElasticSearchTrait::class, 'getElasticsearchUserPassword')]
class ElasticSearchTest extends TestCase {

  /**
   * The DDEV host uses the database prefix; every other host the Pantheon one.
   *
   * @param string $expected
   *   The prefix the URL should resolve to.
   * @param string $es_url
   *   The ElasticSearch URL under test.
   */
  #[DataProvider('indexPrefixCases')]
  public function testIndexPrefix(string $expected, string $es_url): void {
    $method = new \ReflectionMethod(TestRoboFile::class, 'elasticsearchIndexPrefix');
    $this->assertSame($expected, $method->invoke(new TestRoboFile(), $es_url));
  }

  /**
   * Label => [expected prefix, ElasticSearch URL].
   *
   * @return array<string, array{string, string}>
   *   The test cases keyed by a human-readable label.
   */
  public static function indexPrefixCases(): array {
    return [
      'ddev host' => ['elasticsearch_index_db_', 'http://elasticsearch:9200'],
      'hosted cluster' => ['elasticsearch_index_pantheon_', 'https://es.example.com:9243'],
      'localhost' => ['elasticsearch_index_pantheon_', 'http://localhost:9200'],
    ];
  }

  /**
   * The generated string has the requested length and stays in the keyspace.
   */
  public function testRandomStr(): void {
    $method = new \ReflectionMethod(TestRoboFile::class, 'randomStr');
    $robo = new TestRoboFile();

    $string = $method->invoke($robo, 32, 'abc');
    $this->assertSame(32, strlen($string));
    $this->assertSame('', trim($string, 'abc'), 'Only keyspace characters appear.');
    $this->assertNotSame($method->invoke($robo, 32, 'abc'), $string, 'Two calls differ.');
  }

  /**
   * A zero or negative length is rejected.
   */
  public function testRandomStrRejectsEmptyLength(): void {
    $method = new \ReflectionMethod(TestRoboFile::class, 'randomStr');
    $this->expectException(\RangeException::class);
    $method->invoke(new TestRoboFile(), 0);
  }

  /**
   * The stored password is read per environment, with NULL for the misses.
   */
  public function testGetElasticsearchUserPassword(): void {
    $method = new \ReflectionMethod(TestRoboFile::class, 'getElasticsearchUserPassword');
    $robo = new TestRoboFile();

    $working_directory = getcwd();
    $temporary_directory = sys_get_temp_dir() . '/es-secrets-' . uniqid();
    mkdir($temporary_directory);
    chdir($temporary_directory);
    try {
      file_put_contents('server.es.secrets.json', json_encode(['dev' => 'secret-dev']));

      $this->assertSame('secret-dev', $method->invoke($robo, 'server', 'dev'));
      $this->assertNull($method->invoke($robo, 'server', 'live'), 'Missing environment.');
      $this->assertNull($method->invoke($robo, 'other', 'dev'), 'Missing credentials file.');
    }
    finally {
      chdir($working_directory ?: $temporary_directory);
      @unlink($temporary_directory . '/server.es.secrets.json');
      @rmdir($temporary_directory);
    }
  }

}
