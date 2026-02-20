<?php

namespace Drupal\az_blob_fs\PathProcessor;

use Drupal\az_blob_fs\Constants\AzBlobFsConstants;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a path processor to rewrite image styles URLs.
 *
 * As the route system does not allow arbitrary amount of parameters convert
 * the file path to a query parameter on the request.
 *
 * This processor handles Amazon S3 public image style callback:
 *   In order to allow the webserver to serve these files with dynamic args
 *   the route is registered under /azblob/files/styles prefix and change
 *   internally to pass validation and move the file to query parameter.
 *   This file will be processed in
 *   AzBlobFsImageStyleDownloadController::deliver().
 *
 * Private files use the normal private file workflow.
 *
 * @see \Drupal\az_blob_fs\Controller\AzBlobFsImageStyleDownloadController::deliver()
 * @see \Drupal\image\Controller\ImageStyleDownloadController::deliver()
 * @see \Drupal\image\PathProcessor\PathProcessorImageStyles::processInbound()
 */
class AzBlobFsPathProcessorImageStyles implements InboundPathProcessorInterface {

  /**
   * Image style path.
   */
  const IMAGE_STYLE_PATH_PREFIX = '/' . AzBlobFsConstants::SCHEME . '/files/styles/';

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if ($this->isImageStylePath($path)) {
      // Strip out path prefix.
      $rest = preg_replace('|^' . preg_quote(static::IMAGE_STYLE_PATH_PREFIX, '|') . '|', '', $path);
      // Get the image style, scheme and path.
      if (substr_count($rest, '/') >= 2) {
        [$image_style, $scheme, $file] = explode('/', $rest, 3);
        if ($this->isValidScheme($scheme)) {
          // Set the file as query parameter.
          $request->query->set('file', $file);
          $path = static::IMAGE_STYLE_PATH_PREFIX . $image_style . '/' . $scheme;
        }
      }
    }
    return $path;
  }

  /**
   * Check if the path is a azblob image style path.
   *
   * @param string $path
   *   Path to be checked.
   *
   * @return bool
   *   TRUE if path starts with azblob image style prefix, FALSE otherwise.
   */
  private function isImageStylePath(string $path): bool {
    return strpos($path, static::IMAGE_STYLE_PATH_PREFIX) === 0;
  }

  /**
   * Check if scheme is Azure Blob image style supported.
   *
   * @param string $scheme
   *   Passes in file system scheme.
   *
   * @return bool
   *   TRUE if azblob will generate image styles, FALSE otherwise.
   */
  private function isValidScheme(string $scheme): bool {
    return in_array($scheme, ['public', AzBlobFsConstants::SCHEME]);
  }

}
