<?php

namespace Drupal\Tests\remote_stream_wrapper\Kernel;

use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;

/**
 * Tests for the HttpStreamWrapper class.
 *
 * @coversDefaultClass \Drupal\remote_stream_wrapper\StreamWrapper\HttpStreamWrapper
 * @group remote_stream_wrapper
 */
class HttpStreamWrapperTest extends KernelTestBase {

  /**
   * The HTTP stream wrapper.
   *
   * @var \Drupal\remote_stream_wrapper\StreamWrapper\HttpStreamWrapper
   */
  protected $wrapper;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'remote_stream_wrapper',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->wrapper = $this->container->get('stream_wrapper.http');
  }

  /**
   * Test the file stat information from the stream wrapper.
   *
   * @covers ::stream_stat
   * @dataProvider dataStat
   */
  public function testStat($url, $expected_result, $head_response, $get_response): void {
    $client = $this->prepareClient($url, $head_response, $get_response);
    $this->wrapper->setHttpClient($client);

    $stat = $this->wrapper->url_stat($url, 0);
    $this->assertStat($expected_result, $stat);
  }

  /**
   * Assert stat information.
   *
   * @param array|false $expected_stat
   *   The expected file stat array.
   * @param array|false $actual_stat
   *   The actual file stat array.
   */
  public function assertStat($expected_stat, $actual_stat): void {
    if (is_array($actual_stat) && is_array($expected_stat)) {
      $actual_stat = array_intersect_key($actual_stat, $expected_stat);
    }
    $this->assertSame($expected_stat, $actual_stat);
  }

  /**
   * Data provider for testState().
   */
  public function dataStat(): array {
    $data = [];

    // HTTP request sends a 405 Method Not Allowed on HEAD.
    $data[] = [
      'http://www.drupal.org/',
      ['size' => 50],
      405,
      new Response(200, ['Content-Length' => 50]),
    ];

    // HTTP request sends an empty HEAD response.
    $data[] = [
      'http://www.drupal.org/test',
      ['size' => 50],
      new Response(200),
      new Response(200, ['Content-Length' => 50]),
    ];

    // HTTP request sends a valid HEAD response.
    $data[] = [
      'http://www.drupal.org/test.unknown',
      ['size' => 25],
      new Response(200, ['Content-Length' => 25]),
      new Response(200, ['Content-Length' => 50]),
    ];

    // No Content-Type headers, rely on body size.
    $data[] = [
      'http://www.drupal.org/test.unknown',
      ['size' => 10],
      new Response(200),
      new Response(200, [], new Stream(fopen('php://temp', 'r'), ['size' => 10])),
    ];

    // Empty HEAD and GET responses.
    $data[] = [
      'https://www.drupal.org/',
      ['size' => 0],
      new Response(200),
      new Response(200),
    ];

    // Both HEAD and GET are error responses.
    $data[] = [
      'https://www.drupal.org/',
      FALSE,
      404,
      404,
    ];

    return $data;
  }

  /**
   * Prepare the mock HTTP requests and responses.
   *
   * @param \GuzzleHttp\Psr7\Uri|string $url
   *   The request URL.
   * @param \Psr\Http\Message\ResponseInterface|int $head_response
   *   The HEAD request response or an exception HTTP response error code.
   * @param \Psr\Http\Message\ResponseInterface|int $get_response
   *   The GET request response or an exception HTTP response error code.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The HTTP client.
   */
  protected function prepareClient($url, $head_response, $get_response): ClientInterface {
    $client = $this->prophesize('\GuzzleHttp\Client');

    // Convert error codes into exceptions.
    if (is_int($head_response)) {
      $head_response = new ClientException($head_response, new Request('HEAD', ''), new Response($head_response));
    }
    if (is_int($get_response)) {
      $get_response = new ClientException($get_response, new Request('GET', ''), new Response($get_response));
    }

    if ($head_response instanceof Response) {
      $client->request('HEAD', $url, [])->willReturn($head_response);
    }
    elseif ($head_response instanceof \Exception) {
      $client->request('HEAD', $url, [])->willThrow($head_response);
    }
    if ($get_response instanceof Response) {
      $client->request('GET', $url, [])->willReturn($get_response);
    }
    elseif ($get_response instanceof \Exception) {
      $client->request('GET', $url, [])->willThrow($get_response);
    }

    return $client->reveal();
  }

}
