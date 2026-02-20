<?php

namespace Drupal\az_blob_fs;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\key\KeyRepository;

/**
 * Class Azure Blob File System Service.
 */
class AzBlobFsService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The key repository object.
   *
   * @var \Drupal\key\KeyRepository
   */
  protected $keyRepository;

  /**
   * Constructs an AzBlobFsService object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The new database connection object.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory object.
   * @param \Drupal\key\KeyRepository $keyRepository
   *   Key repository object.
   */
  public function __construct(Connection $connection, ConfigFactory $config_factory, KeyRepository $keyRepository) {
    $this->connection = $connection;
    $this->configFactory = $config_factory;
    $this->keyRepository = $keyRepository;
  }

  /**
   * Get Azure Blob storage container.
   *
   * @return string|null
   *   Returns container name.
   */
  public function getBlobContainer(): ?string {
    $container = NULL;
    $config = $this->configFactory->get('az_blob_fs.settings');
    if ($config) {
      $container = $config->get('az_blob_container_name');
    }
    return $container;
  }

  /**
   * Get Azure Blob storage account name.
   *
   * @return string|null
   *   Returns account name.
   */
  public function getAccountName(): ?string {
    $account_name = NULL;
    $config = $this->configFactory->get('az_blob_fs.settings');
    if ($config) {
      $account_name = $config->get('az_blob_account_name');
    }
    return $account_name;
  }

  /**
   * Get proxy client.
   *
   * @param array $data
   *   Array of connection & settings data for client.
   *
   * @return \Drupal\az_blob_fs\AzBlobRestProxyAlter|null
   *   Returns proxy/client or null if failed.
   */
  public function getAzBlobProxyClient(array $data = []): ?AzBlobRestProxyAlter {
    // Make sure we have config.
    if (empty($config)) {
      // Get config.
      $config = $this->configFactory->get('az_blob_fs.settings');
      if ($config) {
        // Get account key.
        $accountKeyName = $config->get('az_blob_account_key_name');
        if (empty($accountKeyName)) {
          return NULL;
        }
        $accountKey = $this->keyRepository->getKey($accountKeyName)->getKeyValue();
        if (empty($accountKey)) {
          return NULL;
        }
        // Setup data.
        $data = [
          'az_blob_account_name' => $config->get('az_blob_account_name'),
          'az_blob_protocol' => $config->get('az_blob_protocol'),
          'az_blob_account_key' => $accountKey,
          'az_blob_local_emulator' => $config->get('az_blob_local_emulator'),
          'az_blob_local_ip' => $config->get('az_blob_local_ip'),
          'az_blob_local_port' => $config->get('az_blob_local_port'),
        ];
      }
    }

    // Get protocol.
    $defaultProtocols = 'https';
    $protocol = 'https';
    if (!empty($data['az_blob_protocol'])) {
      $protocol = $data['az_blob_protocol'];
    }

    // Get blob endpoint.
    $blobEndpoint = NULL;
    if (isset($data['az_blob_local_emulator']) && $data['az_blob_local_emulator']) {
      $blobEndpoint = "{$protocol}://{$data['az_blob_local_ip']}:{$data['az_blob_local_port']}/{$data['az_blob_account_name']}";
    }

    // Build connection string.
    $connectionString = "DefaultEndpointsProtocol={$defaultProtocols};AccountName={$data['az_blob_account_name']};AccountKey={$data['az_blob_account_key']}";

    // Add blob endpoint.
    if (!empty($blobEndpoint)) {
      $connectionString .= ";BlobEndpoint={$blobEndpoint}";
    }

    // Return blob service.
    return AzBlobRestProxyAlter::createBlobService($connectionString);
  }

}
