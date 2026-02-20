<?php

/*
namespace Drupal\custom_override\StreamWrapper;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;

class AzureStreamWrapper implements StreamWrapperInterface {

  private $client;
  private $containerName;
  private $uri;

  public function __construct() {
    $connectionString = " DefaultEndpointsProtocol=https;AccountName=mytestdemostorage;AccountKey=Uq4zsEyJB+GDSsFCDUOxvozI2ANheN/wU6AIYHJQHwzWuA5Mm+Ff4E61z269Okq0t3F9ob/KQ4rr+AStLAIZbA==;EndpointSuffix=core.windows.net";
    $this->client = BlobRestProxy::createBlobService($connectionString);
    $this->containerName = 'demoblobstoragecontainer';
  }

  public function getUri() {
    return $this->uri;
  }

  public function setUri($uri) {
    $this->uri = $uri;
  }

  public function stream_open($path, $mode, $options, &$opened_path) {
    $this->uri = $path;
    return true;
  }

  public function stream_write($data) {
    $blobName = $this->getBlobName($this->uri);
    try {
      $this->client->createBlockBlob($this->containerName, $blobName, $data);
      return strlen($data);
    }
    catch (ServiceException $e) {
      return false;
    }
  }

  public function stream_read($count) {
    $blobName = $this->getBlobName($this->uri);
    try {
      $blob = $this->client->getBlob($this->containerName, $blobName);
      return stream_get_contents($blob->getContentStream(), $count);
    }
    catch (ServiceException $e) {
      return false;
    }
  }

  public function stream_eof() {
    return true;
  }

  public function unlink($uri) {
    $blobName = $this->getBlobName($uri);
    try {
      $this->client->deleteBlob($this->containerName, $blobName);
      return true;
    }
    catch (ServiceException $e) {
      return false;
    }
  }

  // Add other required methods from StreamWrapperInterface...

  private function getBlobName($uri) {
    return ltrim(parse_url($uri, PHP_URL_PATH), '/');
  }
}*/


namespace Drupal\custom_override\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

class AzureStreamWrapper implements StreamWrapperInterface {

  protected $blobClient;
  protected $uri;

  /**
   * Directory listing used by the dir_* methods.
   *
   * @var array
   */
  protected $dir = NULL;

  public function __construct(BlobRestProxy $blobClient) {
    $this->blobClient = $blobClient;
  }

  public function setUri($uri) {
    $this->uri = $uri;
  }

  public function getUri() {
    return $this->uri;
  }

  public function streamOpen($path, $mode, $options, &$opened_path) {
    // Open a stream for the Azure Blob Storage.
    $opened_path = $path;
    return true;
  }

  public function streamClose() {
    // Close the stream.
  }

  public function streamRead($count) {
    // Read from the stream.
  }

  public function streamWrite($data) {
    // Write to the stream.
  }

  public function streamMetadata($path, $option, $value) {
    // Metadata operations.
  }

  public function streamSetOption($option, $arg1, $arg2) {
    // Set stream options.
  }

  public function streamLock($operation) {
    // Lock the stream.
  }

  public function streamStat() {
    // Return file statistics.
  }

  public function streamFlush() {
    // Flush the output buffer.
  }

  public function streamEof() {
    // Check for end-of-file.
  }

  public function unlink($uri) {
    // Unlink the file at the URI.
  }

  public function url_stat($path, $flags) {
    // Return file statistics for the given path.
  }

  public function dir_closedir() {
    // Close the directory handle.
  }

  public function dir_opendir($path, $options) {
    // Open a directory handle.
  }

  public function dir_readdir() {
    // Read an entry from the directory handle.
  }

  public function dir_rewinddir() {
    // Rewind the directory handle.
  }

  public function mkdir($path, $mode, $options) {
    // Make a directory.
  }

  public function rename($path_from, $path_to) {
    // Rename a file or directory.
  }

  public function rmdir($path, $options) {
    // Remove a directory.
  }

  public function getName() {
    return t('Azure Blob Storage');
  }

  public function getDescription() {
    return t('Stream wrapper for Azure Blob Storage.');
  }

  // public function getType() {
  //   return StreamWrapperInterface::EXTERNAL;
  // }
  public static function getType(): int {
    return StreamWrapperInterface::NORMAL;
  }

  public function getExternalUrl() {
    // Generate an external URL for the resource.
    return 'https://mytestdemostorage.blob.core.windows.net/' . $this->uri;
  }
}



