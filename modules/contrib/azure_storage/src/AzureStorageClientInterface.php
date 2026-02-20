<?php

namespace Drupal\azure_storage;

/**
 * Interface AzureStorageClientInterface.
 *
 * @package Drupal\azure_storage
 */
interface AzureStorageClientInterface {

  /**
   * Set a storage queue service.
   *
   * When no connection string is given, one will be looked up from config.
   *
   * @param string $connection_string
   *   Optional Azure connection string.
   *
   * @return $this
   *   AzureClient.
   */
  public function setStorageQueueService($connection_string = NULL);

  /**
   * Get the storage queue service.
   *
   * @return \MicrosoftAzure\Storage\Queue\Internal\IQueue
   *   Azure Queue Service.
   */
  public function getStorageQueueService();

  /**
   * Get a storage queue connection string.
   *
   * @param array $params
   *   Optional parameters with fallback.
   *
   * @return string
   *   Connection string.
   */
  public function getStorageQueueConnectionString(array $params = []) : string;

}
