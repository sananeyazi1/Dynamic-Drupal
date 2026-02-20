<?php

namespace Drupal\azure_blob_storage\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller to test module functions.
 *
 * Class AzureStorageTestController.
 *
 * @package Drupal\azure_blob_storage\Controller
 */
class AzureStorageTestController extends ControllerBase {

  /**
   * Create service method.
   *
   */
  public function test() {
    $result = uploadToAzureStorage();
    echo $result; die;
  }

}
