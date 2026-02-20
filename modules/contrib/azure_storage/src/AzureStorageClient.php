<?php

namespace Drupal\azure_storage;

use Drupal\Core\Config\ConfigFactoryInterface;
use MicrosoftAzure\Storage\Queue\QueueRestProxy;
use Psr\Log\LoggerInterface;

/**
 * Class AzureStorageClient.
 *
 * @package Drupal\azure_storage
 */
class AzureStorageClient implements AzureStorageClientInterface {

  /**
   * The Azure Storage settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Azure Queue client.
   *
   * @var \MicrosoftAzure\Storage\Queue\Internal\IQueue
   */
  protected $queueClient;

  /**
   * AzureStorageClient constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A configuration factory instance.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->config = $config_factory->get('azure_storage.settings');
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function setStorageQueueService($connection_string = NULL) {
    if ($connection_string === NULL) {
      $connection_string = $this->getStorageQueueConnectionString();
    }
    $this->queueClient = QueueRestProxy::createQueueService($connection_string);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageQueueService() {
    if ($this->queueClient === NULL) {
      $this->setStorageQueueService();
    }
    return $this->queueClient;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageQueueConnectionString(array $params = []) : string {
    $protocol = $params['protocol'] ?? $this->config->get('protocol');
    $account_name = $params['account_name'] ?? $this->config->get('account_name');
    $account_key = $params['account_key'] ?? AzureStorage::getAccountKey();
    $endpoint_suffix = $params['endpoint_suffix'] ?? $this->config->get('endpoint_suffix');
    return "DefaultEndpointsProtocol=$protocol;AccountName=$account_name;AccountKey=$account_key;EndpointSuffix=$endpoint_suffix";
  }

}
