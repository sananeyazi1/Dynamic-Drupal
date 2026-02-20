<?php

namespace Drupal\az_blob_fs\StreamWrapper;

use Drupal\az_blob_fs\AzBlobFsException;
use Drupal\az_blob_fs\AzBlobRestProxyAlter;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use MicrosoftAzure\Storage\Blob\Models\Blob;
use MicrosoftAzure\Storage\Blob\Models\BlobPrefix;
use MicrosoftAzure\Storage\Blob\Models\GetBlobResult;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

/**
 * Azure Blob File System Stream Wrapper.
 */
class AzBlobFsStreamWrapper {

  use StringTranslationTrait;

  /**
   * Module configuration for stream.
   *
   * @var array
   */
  protected $config = [];

  /**
   * Microsoft Blob client.
   *
   * @var \MicrosoftAzure\Storage\Blob\BlobRestProxy
   */
  protected $client = NULL;

  /**
   * The Azure Blob Drupal Service.
   *
   * @var \Drupal\az_blob_fs\AzBlobFsService
   */
  protected $azBlob = NULL;

  /**
   * Mode in which the stream was opened.
   *
   * @var string
   */
  protected $mode;

  /**
   * The Azure Storage blob container.
   *
   * @var string
   */
  protected $container;

  /**
   * Instance uri referenced as "<scheme>://key".
   *
   * @var string
   */
  protected $uri = NULL;

  /**
   * Directory listing used by the dir_* methods.
   *
   * @var array
   */
  protected $dir = NULL;

  /**
   * The iterator.
   *
   * @var \ArrayIterator
   */
  protected $iterator;

  /**
   * Temporary file handle.
   *
   * @var resource
   */
  protected $temporaryFileHandle = NULL;

  /**
   * Stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  protected $streamWrapperManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Constructs a new AzBlobFsStreamWrapper object.
   */
  public function __construct() {
    $this->loadConfig();

    // Check for key config data. If empty, let's halt.
    if (empty($this->config['az_blob_account_name']) || empty($this->config['az_blob_account_key_name'])) {
      throw new AzBlobFsException('Azure Blob Storage account name or key is not set.');
    }

    $this->container = $this->config['az_blob_container_name'];
    $this->streamWrapperManager = \Drupal::service('stream_wrapper_manager');
    $this->logger = \Drupal::logger('az_blob_fs');
    $this->azBlob = \Drupal::service('az_blob_fs');
    $this->fileSystem = \Drupal::service('file_system');
    $this->client = $this->getClient();
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
   * Get Azure Blob client.
   *
   * @return \Drupal\az_blob_fs\AzBlobRestProxyAlter|null
   *   Returns Azure Blob Rest Proxy.
   */
  public function getClient(): ?AzBlobRestProxyAlter {
    return $this->azBlob->getAzBlobProxyClient($this->config);
  }

  /**
   * Check if the Azure Blob Storage client is initialized.
   *
   * @return bool
   *   Returns status of client.
   */
  protected function isClientReady(): bool {
    if (!$this->client) {
      $this->logger->error($this->t('Azure Blob Storage client is not set up.'));
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get Azure Blob from the remote storage.
   *
   * @param string $path
   *   The path, or "name" of the blob.
   *
   * @return \MicrosoftAzure\Storage\Blob\Models\GetBlobResult|null
   *   Returns a blob if it was found, or NULL otherwise.
   */
  protected function getBlob(string $path): ?GetBlobResult {
    if (!$this->isClientReady()) {
      return NULL;
    }

    // Attempt to get file from path.
    try {
      // If it was found, return it straight up.
      return $this->getClient()->getBlob($this->container, $path);
    }
    catch (ServiceException $e) {
      if ($e->getCode() !== 404) {
        watchdog_exception('Azure Blob Stream Exception (getBlob)', $e);
      }
      return NULL;
    }
    catch (\Exception $e) {
      watchdog_exception('Azure Blob Stream Exception (getBlob)', $e);
      return NULL;
    }
  }

  /**
   * Rename blob.
   *
   * In Azure Blob Storage, we have to copy the existing blob to a new one with
   * the new name, then delete the original.
   *
   * Now...Renaming folders might be very heavy. We'll see about adding that in
   * the future.
   *
   * @param string $path_from
   *   Current path.
   * @param string $path_to
   *   New path.
   *
   * @return bool
   *   Returns TRUE if the operation was successful, FALSE otherwise.
   */
  protected function renameBlob(string $path_from, string $path_to): bool {
    if (!$this->isClientReady()) {
      return FALSE;
    }

    try {
      $this->getClient()
        ->copyBlob($this->container, $path_to, $this->container, $path_from);
      $this->getClient()->deleteBlob($this->container, $path_from);
      clearstatcache(TRUE, $path_from);
      clearstatcache(TRUE, $path_to);
    }
    catch (ServiceException $e) {
      watchdog_exception('Azure Blob Stream Exception (rename)', $e);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Delete a blob of virtual folder at a given path.
   *
   * @param string $path
   *   The path that will be deleted.
   * @param array $options
   *   Options to affect the delete operation.
   *
   * @return bool
   *   Returns TRUE if the operation was successful, FALSE otherwise.
   */
  protected function deleteRemotePath(string $path, array $options = ['recursive' => FALSE]): bool {
    if (!$this->isClientReady()) {
      return FALSE;
    }

    // Get the target.
    $path = $this->streamWrapperManager->getTarget($path);

    // First, we should check if the path is a file or directory.
    $pathInfo = $this->remotePathInfo($path);

    // If this remote path doesn't exist, return.
    if (!$pathInfo[0]) {
      return FALSE;
    }

    // Otherwise, we handle the case where this is a file.
    if ($pathInfo[1] === 'file') {
      $del = TRUE;
      try {
        $this->getClient()->deleteBlob($this->container, $path);
      }
      catch (ServiceException $e) {
        watchdog_exception('Azure Blob Stream Exception (deleteRemotePath)', $e);
        $del = FALSE;
      }
      return $del;
    }

    // Otherwise, we handle the case where this is a folder.
    if ($pathInfo[1] === 'folder') {
      $items = $pathInfo[2];
      if (!empty($items) && $options['recursive']) {
        /** @var \MicrosoftAzure\Storage\Blob\Models\Blob $blob */
        foreach ($items as $item) {
          if ($item instanceof Blob) {
            $blobName = $blob->getName();
            $this->deleteRemotePath($blobName);
          }
          elseif ($item instanceof BlobPrefix) {
            $this->deleteRemotePath($item->getName());
          }
        }
      }
      else {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get information on a remote path towards the Azure Blob Storage.
   *
   * This information will tell you if the path is void, is a blob block or is
   * a virtual folder.
   *
   * @param string $path
   *   The path to get information on.
   *
   * @return array
   *   An array of relevant information.
   */
  protected function remotePathInfo(string $path): array {
    // Returns information about whether a file exists or not.
    // First, if we find a blob, that means we have ourselves a file.
    $blob = $this->getBlob($path);
    if ($blob !== NULL) {
      return [TRUE, 'file', $blob];
    }

    // If the above doesn't go through, we check if it's a directory.
    $folder = $this->getVirtualFolder($path);
    if ($folder !== NULL) {
      return [TRUE, 'folder', $folder];
    }

    // Otherwise, return false, wrapped in a beautiful array.
    return [FALSE];
  }

  /**
   * Get Azure Virtual Folder from the remote storage.
   *
   * Azure doesn't have REAL directories or folders. They're virtual. Only the
   * full path of each blob is saved.
   *
   * @param string $path
   *   The path to the virtual folder.
   * @param array $options
   *   Custom options to affect the returned output.
   *
   * @return array|\MicrosoftAzure\Storage\Blob\Models\Blob[]|null
   *   Return an array of Blobs/BlobPrefixes, or NULL if nothing was found.
   */
  protected function getVirtualFolder(string $path, array $options = ['exclude_dirs' => FALSE]): ?array {
    if (!$this->isClientReady()) {
      return NULL;
    }

    // Set our prefix to the provided path.
    // The prefix will allow the following code to search for relevant blobs.
    // If the prefix is just a slash, we set the prefix to empty.
    // This will make us list all blobs at the root.
    $prefix = $path;
    if ($path === '/') {
      $prefix = '';
    }
    // Otherwise, if our prefix doesn't end with a slash, append one.
    elseif (substr($path, -1) !== '/') {
      $prefix = $path . '/';
    }

    // Set up our list options.
    $listOptions = new ListBlobsOptions();
    $listOptions->setPrefix($prefix);
    $listOptions->setDelimiter('/');

    try {
      $result = $this->getClient()->listBlobs($this->container, $listOptions);
      $folderData = $result->getBlobs() ?? [];
      if (!$options['exclude_dirs']) {
        $blobPrefixes = $result->getBlobPrefixes() ?? [];
        // Merge in any blob prefixes as well.
        $folderData = array_merge($folderData, $blobPrefixes);
      }
    }
    catch (ServiceException $e) {
      watchdog_exception('Azure Blob Stream Exception (getVirtualFolder)', $e);
      $folderData = [];
    }

    // If there are blobs, then we have ourselves a folder.
    if (!empty($folderData)) {
      return $folderData;
    }

    // Otherwise, return null. Nothing found.
    return NULL;
  }

  /**
   * Get content types and extension mappings.
   *
   * @return array
   *   Array of extension to content type mappings.
   */
  public function getContentTypes(): array {
    return [
      '3gp' => 'video/3gpp',
      '7z' => 'application/x-7z-compressed',
      'aac' => 'audio/x-aac',
      'ai' => 'application/postscript',
      'aif' => 'audio/x-aiff',
      'asc' => 'text/plain',
      'asf' => 'video/x-ms-asf',
      'atom' => 'application/atom+xml',
      'avi' => 'video/x-msvideo',
      'bmp' => 'image/bmp',
      'bz2' => 'application/x-bzip2',
      'cer' => 'application/pkix-cert',
      'crl' => 'application/pkix-crl',
      'crt' => 'application/x-x509-ca-cert',
      'css' => 'text/css',
      'csv' => 'text/csv',
      'cu' => 'application/cu-seeme',
      'deb' => 'application/x-debian-package',
      'doc' => 'application/msword',
      'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'dvi' => 'application/x-dvi',
      'eot' => 'application/vnd.ms-fontobject',
      'eps' => 'application/postscript',
      'epub' => 'application/epub+zip',
      'etx' => 'text/x-setext',
      'flac' => 'audio/flac',
      'flv' => 'video/x-flv',
      'gif' => 'image/gif',
      'gz' => 'application/gzip',
      'htm' => 'text/html',
      'html' => 'text/html',
      'ico' => 'image/x-icon',
      'ics' => 'text/calendar',
      'ini' => 'text/plain',
      'iso' => 'application/x-iso9660-image',
      'jar' => 'application/java-archive',
      'jpe' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'jpg' => 'image/jpeg',
      'js' => 'text/javascript',
      'json' => 'application/json',
      'latex' => 'application/x-latex',
      'log' => 'text/plain',
      'm4a' => 'audio/mp4',
      'm4v' => 'video/mp4',
      'mid' => 'audio/midi',
      'midi' => 'audio/midi',
      'mov' => 'video/quicktime',
      'mkv' => 'video/x-matroska',
      'mp3' => 'audio/mpeg',
      'mp4' => 'video/mp4',
      'mp4a' => 'audio/mp4',
      'mp4v' => 'video/mp4',
      'mpe' => 'video/mpeg',
      'mpeg' => 'video/mpeg',
      'mpg' => 'video/mpeg',
      'mpg4' => 'video/mp4',
      'oga' => 'audio/ogg',
      'ogg' => 'audio/ogg',
      'ogv' => 'video/ogg',
      'ogx' => 'application/ogg',
      'pbm' => 'image/x-portable-bitmap',
      'pdf' => 'application/pdf',
      'pgm' => 'image/x-portable-graymap',
      'png' => 'image/png',
      'pnm' => 'image/x-portable-anymap',
      'ppm' => 'image/x-portable-pixmap',
      'ppt' => 'application/vnd.ms-powerpoint',
      'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'ps' => 'application/postscript',
      'qt' => 'video/quicktime',
      'rar' => 'application/x-rar-compressed',
      'ras' => 'image/x-cmu-raster',
      'rss' => 'application/rss+xml',
      'rtf' => 'application/rtf',
      'sgm' => 'text/sgml',
      'sgml' => 'text/sgml',
      'svg' => 'image/svg+xml',
      'swf' => 'application/x-shockwave-flash',
      'tar' => 'application/x-tar',
      'tif' => 'image/tiff',
      'tiff' => 'image/tiff',
      'torrent' => 'application/x-bittorrent',
      'ttf' => 'application/x-font-ttf',
      'txt' => 'text/plain',
      'wav' => 'audio/x-wav',
      'webm' => 'video/webm',
      'webp' => 'image/webp',
      'wma' => 'audio/x-ms-wma',
      'wmv' => 'video/x-ms-wmv',
      'woff' => 'application/x-font-woff',
      'wsdl' => 'application/wsdl+xml',
      'xbm' => 'image/x-xbitmap',
      'xls' => 'application/vnd.ms-excel',
      'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'xml' => 'application/xml',
      'xpm' => 'image/x-xpixmap',
      'xwd' => 'image/x-xwindowdump',
      'yaml' => 'text/yaml',
      'yml' => 'text/yaml',
      'zip' => 'application/zip',
    ];
  }

}
