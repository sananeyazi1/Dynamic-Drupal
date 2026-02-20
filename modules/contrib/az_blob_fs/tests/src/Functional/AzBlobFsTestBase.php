<?php

namespace Drupal\Tests\az_blob_fs\Functional;

use Drupal\Core\Config\Config;
use Drupal\Tests\BrowserTestBase;

/**
 * Azure Blob Storage File System Test Base.
 *
 * Provides a base for BrowserTest to execute against.
 *
 * @group az_blob_fs
 */
abstract class AzBlobFsTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'az_blob_fs',
    'image',
    'key',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Azure Blog Storage config.
   *
   * @var array
   */
  protected $azBlobFsConfig;

  /**
   * Azure Blog Storage File Service.
   *
   * @var \Drupal\az_blob_fs\AzBlobFsService
   */
  protected $azBlobFs;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->prepareConfig($this->config('az_blob_fs.settings'));
    $this->azBlobFs = \Drupal::service('az_blob_fs');
  }

  /**
   * Prepare configuration.
   *
   * @param \Drupal\Core\Config\Config $config
   *   A config object.
   */
  protected function prepareConfig(Config $config) {
    $this->azBlobFsConfig = [
      'az_blob_account_name' => 'devstoreaccount1',
      'az_blob_account_key_name' => 'azure_account_key',
      'az_blob_account_key' => 'Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==',
      'az_blob_container_name' => 'devstorecontainer',
      'az_blob_protocol' => 'https',
      'az_blob_cdn_host_name' => 'drupal.azureedge.net',
      'az_blob_initial_image_styles' => [],
      'az_blob_queue_image_styles' => [],
    ];

    // Set configuration.
    $config
      ->set('az_blob_account_name', $this->azBlobFsConfig['az_blob_account_name'])
      ->set('az_blob_container_name', $this->azBlobFsConfig['az_blob_container_name'])
      ->set('az_blob_protocol', $this->azBlobFsConfig['az_blob_protocol'])
      ->set('az_blob_cdn_host_name', $this->azBlobFsConfig['az_blob_cdn_host_name'])
      ->save();
  }

}
