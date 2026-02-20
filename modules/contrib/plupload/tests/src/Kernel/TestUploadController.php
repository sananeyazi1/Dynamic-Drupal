<?php

namespace Drupal\Tests\plupload\Kernel;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\plupload\UploadController;

/**
 * Mocked Plupload upload controller.
 */
class TestUploadController extends UploadController implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  protected function isUploadedFile(string $filename): bool {
    return file_exists($filename);
  }

}
