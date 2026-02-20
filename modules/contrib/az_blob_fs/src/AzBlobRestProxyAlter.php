<?php

namespace Drupal\az_blob_fs;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\Internal\Authentication\SharedAccessSignatureAuthScheme;
use MicrosoftAzure\Storage\Common\Internal\Authentication\SharedKeyAuthScheme;
use MicrosoftAzure\Storage\Common\Internal\Middlewares\CommonRequestMiddleware;
use MicrosoftAzure\Storage\Blob\Internal\BlobResources as Resources;
use MicrosoftAzure\Storage\Common\Internal\Resources as ResourcesAlias;
use MicrosoftAzure\Storage\Common\Internal\StorageServiceSettings;
use MicrosoftAzure\Storage\Common\Internal\Utilities;
use function watchdog_exception;

/**
 * Class Azure Blob Rest Proxy Alter.
 */
class AzBlobRestProxyAlter extends BlobRestProxy {

  /**
   * Azure configuration and settings.
   *
   * @var array
   */
  protected $config = [];

  /**
   * The constructor for AzBlobRestProxyAlter.
   *
   * @param string $primaryUri
   *   The primary uri.
   * @param string $secondaryUri
   *   The secondary uri.
   * @param string $accountName
   *   The account name.
   * @param array $options
   *   The options.
   */
  public function __construct($primaryUri, $secondaryUri, $accountName, array $options = []) {
    parent::__construct($primaryUri, $secondaryUri, $accountName, $options);
    $this->loadConfig();
  }

  /**
   * Load up configuration.
   */
  private function loadConfig() {
    // Load up config.
    $config = \Drupal::config('az_blob_fs.settings');
    foreach ($config->get() as $prop => $value) {
      $this->config[$prop] = $value;
    }
  }

  /**
   * Creates URI path for blob or container.
   *
   * @param string $container
   *   The container name.
   * @param string $blob
   *   The blob name.
   *
   * @return string
   *   Returns the path.
   */
  protected function createPath(string $container, string $blob = ''): string {
    if (empty($blob) && ($blob != '0')) {
      return empty($container) ? '/' : $container;
    }

    $encodedBlob = urlencode($blob);

    // Un-encode the forward slashes to match what the server expects.
    $encodedBlob = str_replace('%2F', '/', $encodedBlob);

    // Un-encode the backward slashes to match what the server expects.
    $encodedBlob = str_replace('%5C', '/', $encodedBlob);

    // Re-encode the spaces (encoded as space) to the % encoding.
    $encodedBlob = str_replace('+', '%20', $encodedBlob);

    // Empty container means accessing default container.
    if (empty($container)) {
      return $encodedBlob;
    }

    return '/' . $container . '/' . $encodedBlob;
  }

  /**
   * Creates full URI to the given blob.
   *
   * @param string $container
   *   The container name.
   * @param string $blob
   *   The blob name.
   *
   * @return string
   *   Returns the blob url.
   */
  public function getBlobUrl($container, $blob): string {
    // Get blob.
    $encodedBlob = $this->createPath($container, $blob);

    // Get primary uri.
    $uri = $this->getPsrPrimaryUri();
    // Make sure local emulator is not turned on.
    if ($uri) {
      // Set scheme/protocol.
      if (!empty($this->config['az_blob_protocol']) && !$this->config['az_blob_local_emulator']) {
        $uri = $uri->withScheme($this->config['az_blob_protocol']);
      }
      // Set host if CDN hostname is configured.
      if (!empty($this->config['az_blob_cdn_host_name'])) {
        $uri = $uri->withHost($this->config['az_blob_cdn_host_name']);
      }
    }

    // Get path.
    $exPath = $uri->getPath();
    if ($exPath != '') {
      // Remove the duplicated slash in the path.
      $encodedBlob = str_replace('//', '/', $exPath . $encodedBlob);
    }

    // Return blob url.
    return (string) $uri->withPath($encodedBlob);
  }

  /**
   * Renames a blob. Actually makes a copy of it and removes the old one.
   *
   * @param mixed $source_container
   *   The source container.
   * @param mixed $source_name
   *   The source name.
   * @param mixed $destination_container
   *   The destination container.
   * @param mixed $destination_name
   *   The destination name.
   *
   * @return bool
   *   Returns TRUE if the operation was successful, FALSE otherwise.
   */
  public function renameBlob($source_container, $source_name, $destination_container, $destination_name): bool {
    try {
      $this->copyBlob($destination_container, $destination_name, $source_container, $source_name);
      $this->deleteBlob($source_container, $source_name);
    }
    catch (ServiceException $e) {
      watchdog_exception('Azure Blob Rest Proxy Alter Exception (renameBlob)', $e);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Indicates if provided uri is a file.
   *
   * @param string $uri
   *   The uri to validate.
   *
   * @return bool
   *   Returns TRUE if the uri is a file, FALSE otherwise.
   */
  public function uriIsFile(string $uri): bool {
    $parts = explode('/', $uri);
    $file_name = end($parts);

    return stripos($file_name, '.') !== FALSE;
  }

  /**
   * Get prefixed blob.
   *
   * @param mixed $container
   *   The container.
   * @param mixed $uri
   *   The container.
   *
   * @return false|\MicrosoftAzure\Storage\Blob\Models\GetBlobResult
   *   Returns the blob results.
   */
  public function getPrefixedBlob($container, $uri) {
    try {
      return $this->getBlob($container, $uri);
    }
    catch (ServiceException $e) {
      watchdog_exception('Azure Blob Rest Proxy Alter Exception (getPrefixedBlob)', $e);
      return FALSE;
    }
  }

  /**
   * Create blob service wrapper.
   *
   * @param string $connectionString
   *   The connection string.
   * @param array $options
   *   The options.
   *
   * @return \Drupal\az_blob_fs\AzBlobRestProxyAlter
   *   Returns the  Azure Blob Rest Proxy Alter.
   */
  public static function createBlobService($connectionString, array $options = []): AzBlobRestProxyAlter {
    $settings = StorageServiceSettings::createFromConnectionString(
      $connectionString
    );

    $primaryUri = Utilities::tryAddUrlScheme(
      $settings->getBlobEndpointUri()
    );

    $secondaryUri = Utilities::tryAddUrlScheme(
      $settings->getBlobSecondaryEndpointUri()
    );

    $blobWrapper = new AzBlobRestProxyAlter(
      $primaryUri,
      $secondaryUri,
      $settings->getName(),
      $options
    );

    // Getting authentication scheme.
    if ($settings->hasSasToken()) {
      $authScheme = new SharedAccessSignatureAuthScheme(
        $settings->getSasToken()
      );
    }
    else {
      $authScheme = new SharedKeyAuthScheme(
        $settings->getName(),
        $settings->getKey()
      );
    }

    // Adding common request middleware.
    $commonRequestMiddleware = new CommonRequestMiddleware(
      $authScheme,
      Resources::STORAGE_API_LATEST_VERSION,
      Resources::BLOB_SDK_VERSION
    );
    $blobWrapper->pushMiddleware($commonRequestMiddleware);

    return $blobWrapper;
  }

}
