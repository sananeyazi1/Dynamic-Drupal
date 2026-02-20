<?php

namespace Drupal\remote_stream_wrapper\File\MimeType;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileSystemInterface;
use Drupal\remote_stream_wrapper\HttpClientTrait;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Makes possible to guess the MIME type of a remote file.
 */
class HttpMimeTypeGuesser implements MimeTypeGuesserInterface {

  use HttpClientTrait;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The extension guesser.
   *
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected $extensionGuesser;

  /**
   * Constructs a new HttpMimeTypeGuesser.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Symfony\Component\Mime\MimeTypeGuesserInterface $extension_guesser
   *   The extension guesser.
   */
  public function __construct(FileSystemInterface $file_system, MimeTypeGuesserInterface $extension_guesser) {
    $this->fileSystem = $file_system;
    $this->extensionGuesser = $extension_guesser;
  }

  /**
   * {@inheritdoc}
   */
  public function guessMimeType(string $path): ?string {
    // Ignore non-external URLs.
    if (!UrlHelper::isExternal($path)) {
      return NULL;
    }

    // Attempt to parse out the mime type if the URL contains a filename.
    if ($filename = $this->parseFileNameFromUrl($path)) {
      $mimetype = $this->extensionGuesser->guessMimeType($filename);

      if ($mimetype !== 'application/octet-stream') {
        // Only return the guessed mime type if it found a valid match
        // instead of returning the default mime type.
        return $mimetype;
      }
    }

    try {
      $response = $this->requestTryHeadLookingForHeader($path, 'Content-Type');
      if ($mimetype = $this->getMimeTypeFromResponseHeaders($response)) {
        return $mimetype;
      }
    }
    catch (\Exception $exception) {
      watchdog_exception('remote_stream_wrapper', $exception);
    }

    return NULL;
  }

  /**
   * Extract the mime type from a HTTP response using Content-Type headers.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The HTTP request response.
   *
   * @return string|null
   *   The mime type or NULL if the response did not include a content-type
   *   header.
   */
  protected function getMimeTypeFromResponseHeaders(ResponseInterface $response): ?string {
    if ($response->hasHeader('Content-Type')) {
      $header = mb_strtolower($response->getHeaderLine('Content-Type'));
      [$mimetype] = explode(';', $header, 2);
      return $mimetype;
    }
    return NULL;
  }

  /**
   * Parse a file name from a URI.
   *
   * This also requires the filename to have an extension.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string|false
   *   The filename if it could be parsed from the URI, or FALSE otherwise.
   */
  public function parseFileNameFromUrl($uri) {
    // Extract the path part from the URL, ignoring query strings or fragments.
    if ($path = parse_url($uri, PHP_URL_PATH)) {
      $filename = $this->fileSystem->basename($path);
      // Filename must contain a period in order to find a valid extension.
      // If the filename does not contain an extension, then guessMimeType()
      // will always return the default 'application/octet-stream' value.
      if (strpos($filename, '.') > 0) {
        return $filename;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isGuesserSupported(): bool {
    return TRUE;
  }

}
