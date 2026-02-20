<?php

namespace Drupal\remote_stream_wrapper\Entity;

use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\image\Entity\ImageStyle as OriginalImageStyle;

/**
 * Overrides the ImageStyle entity class to support remote image derivatives.
 */
class ImageStyle extends OriginalImageStyle {

  /**
   * {@inheritdoc}
   */
  public function buildUri($uri) {
    if ($this->fileIsUriRemote($uri)) {
      // Reroute derivatives of remote images through the default file system.
      $scheme = StreamWrapperManager::getScheme($uri);
      $path = StreamWrapperManager::getTarget($uri);
      return $this->fileDefaultScheme() . '://styles/' . $this->id() . '/' . $scheme . '/' . $this->addExtension($path);
    }
    else {
      return parent::buildUri($uri);
    }
  }

  /**
   * Provides a wrapper for file_is_uri_remote() to allow unit testing.
   *
   * @param string $uri
   *   A file URI.
   *
   * @return bool
   *   TRUE if the file is remote, or FALSE otherwise.
   *
   * @todo Convert file_is_uri_remote() into a proper injectable service.
   */
  public function fileIsUriRemote($uri) {
    return file_is_uri_remote($uri);
  }

}
