<?php

namespace Drupal\Tests\remote_stream_wrapper\Kernel;

use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for the HttpMimeTypeGuesser class.
 *
 * @coversDefaultClass \Drupal\remote_stream_wrapper\File\MimeType\HttpMimeTypeGuesser
 * @group remote_stream_wrapper
 */
class HttpMimeTypeGuesserTest extends KernelTestBase {

  /**
   * The mime type guesser.
   *
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected $guesser;

  /**
   * The HTTP mime type guesser.
   *
   * @var \Drupal\remote_stream_wrapper\File\MimeType\HttpMimeTypeGuesser
   */
  protected $httpGuesser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['remote_stream_wrapper'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->guesser = $this->container->get('file.mime_type.guesser');
    $this->httpGuesser = $this->container->get('file.mime_type.guesser.http');
  }

  /**
   * Test the mime type guessing with various HTTP URLs.
   *
   * @covers ::parseFileNameFromUrl
   * @dataProvider dataParseFileNameFromUrl
   */
  public function testParseFileNameFromUrl($url, $expected_result): void {
    $this->assertEquals($expected_result, $this->httpGuesser->parseFileNameFromUrl($url));
  }

  /**
   * Test the mime type guessing with various HTTP URLs.
   *
   * @covers ::guess
   * @dataProvider dataHttpMimetypeGuessing
   */
  public function testHttpMimeTypeGuessing($url, $expected_result, $head_response = NULL, $get_response = NULL): void {
    $client = $this->prepareClient($url, $head_response, $get_response);
    $this->httpGuesser->setHttpClient($client);

    $this->assertEquals($expected_result, $this->guesser->guessMimeType($url));
  }

  /**
   * Data provider for testParseFileNameFromUrl().
   */
  public function dataParseFileNameFromUrl(): array {
    return [
      ['http://www.example.com/file.txt', 'file.txt'],
      // Test adding query strings and fragments which should be ignored.
      ['http://www.example.com/test/file.txt.pdf?extension=.gif', 'file.txt.pdf'],
      ['http://www.example.com/FILE.PDF#foo', 'FILE.PDF'],
      ['http://www.example.com/', FALSE],
      ['http://www.example.com/test', FALSE],
      ['//www.example.com/test.unknown', 'test.unknown'],
    ];
  }

  /**
   * Data provider for testHttpMimeTypeGuessing().
   */
  public function dataHttpMimetypeGuessing(): array {
    $data = [];

    // Filename can be extracted from URL, no HTTP requests.
    $data[] = [
      'http://www.drupal.org/file.txt',
      'text/plain',
    ];

    // Filename in upper case.
    $data[] = [
      'http://www.drupal.org/FILE.TXT',
      'text/plain',
    ];

    // Test adding query strings and fragments which should be ignored.
    $data[] = [
      'http://www.drupal.org/test/file.txt?extension=.gif',
      'text/plain',
    ];
    $data[] = [
      'https://www.drupal.org/file.pdf#foo',
      'application/pdf',
    ];

    // HTTP request sends a 405 Method Not Allowed on HEAD.
    $data[] = [
      'http://www.drupal.org/',
      'html/get',
      405,
      new Response(200, ['Content-Type' => 'html/get']),
    ];

    // HTTP request sends an empty HEAD response.
    $data[] = [
      'http://www.drupal.org/test',
      'html/get',
      new Response(200),
      new Response(200, ['Content-Type' => 'html/get']),
    ];

    // HTTP request sends a valid HEAD response.
    $data[] = [
      '//www.drupal.org/test.unknown',
      'html/head',
      new Response(200, ['Content-Type' => 'html/head']),
      new Response(200, ['Content-Type' => 'html/get']),
    ];

    // Both HEAD and GET are error responses.
    $data[] = [
      'https://www.drupal.org/',
      'application/octet-stream',
      404,
      404,
    ];

    // Non-HTTP URLs should bypass the HTTP guesser.
    $data[] = [
      'public://test.unknown',
      'application/octet-stream',
      new Response(200, ['Content-Type' => 'html/head']),
    ];
    $data[] = [
      'core/CHANGELOG.txt',
      'text/plain',
      new Response(200, ['Content-Type' => 'html/head']),
    ];

    // Test a upper case Content-Type header with additional attributes.
    $data[] = [
      'https://www.example.com/',
      'text/html',
      new Response(200, ['Content-Type' => 'TEXT/HTML; charset=UTF-8']),
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
