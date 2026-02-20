<?php

namespace Drupal\az_blob_fs\StreamWrapper;

use Drupal\az_blob_fs\Constants\AzBlobFsConstants;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Psr7\MimeType;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use MicrosoftAzure\Storage\Blob\Models\Block;
use MicrosoftAzure\Storage\Blob\Models\BlockList;
use MicrosoftAzure\Storage\Blob\Models\CommitBlobBlocksOptions;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\Internal\Resources;
use Psr\Http\Message\StreamInterface;
use function _PHPStan_9a6ded56a\RingCentral\Psr7\mimetype_from_extension;

/**
 * Azure Blob Filesystem Stream.
 */
class AzBlobFsStream extends AzBlobFsStreamWrapper implements StreamWrapperInterface, StreamInterface {

  use StreamDecoratorTrait;
  use StringTranslationTrait;

  // @codingStandardsIgnoreStart
  /**
   * Constructs a new AzBlobFsStream object.
   *
   * Dependency injection will not work here, since stream wrappers
   * are not loaded the normal way: PHP creates them automatically
   * when certain file functions are called.  This prevents us from
   * passing arguments to the constructor, which we'd need to do in
   * order to use standard dependency injection as is typically done
   * in Drupal.
   *
   * @throws \Drupal\az_blob_fs\AzBlobFsException
   */
  public function __construct() {
    parent::__construct();
  }
  // @codingStandardsIgnoreEnd

  /**
   * Returns the type of stream wrapper.
   *
   * @return int
   *   Type of stream wrapper.
   */
  public static function getType(): int {
    return StreamWrapperInterface::NORMAL;
  }

  /**
   * Returns the name of the stream wrapper for use in the UI.
   *
   * @return string
   *   The stream wrapper name.
   */
  public function getName(): string {
    return $this->t('Azure Blob Storage');
  }

  /**
   * Returns the description of the stream wrapper for use in the UI.
   *
   * @return string
   *   The stream wrapper description.
   */
  public function getDescription(): string {
    return $this->t('Files served from Azure Blob Storage.');
  }

  /**
   * Gets the path that the wrapper is responsible for.
   *
   * This function isn't part of DrupalStreamWrapperInterface, but the rest
   * of Drupal calls it as if it were, so we need to define it.
   *
   * @return string
   *   The empty string. Since this is a remote stream wrapper,
   *   it has no directory path.
   *
   * @see \Drupal\Core\File\LocalStream::getDirectoryPath()
   */
  public function getDirectoryPath(): string {
    return '';
  }

  /**
   * Sets the absolute stream resource URI.
   *
   * This allows you to set the URI. Generally is only called by the factory
   * method.
   *
   * @param string $uri
   *   A string containing the URI that should be used for this instance.
   */
  public function setUri($uri) {
    $this->uri = $uri;
  }

  /**
   * Returns the stream resource URI.
   *
   * @return string
   *   Returns the current URI of the instance.
   */
  public function getUri(): string {
    return $this->uri;
  }

  /**
   * Returns a web accessible URL for the resource.
   *
   * @return string
   *   Returns a string containing a web accessible URL for the resource.
   */
  public function getExternalUrl(): string {
    // Get the target destination without the scheme.
    $target = $this->streamWrapperManager->getTarget($this->uri);

    // Handle image styles.
    if (strpos($target, 'styles/') === 0) {
      // If the style derivative does not exist yet, we return to our custom
      // image style path handler.
      if (!file_exists(AzBlobFsConstants::SCHEME . '://' . $target)) {
        return $GLOBALS['base_url'] . '/' . AzBlobFsConstants::SCHEME . '/files/' . UrlHelper::encodePath($target);
      }
    }

    // If there is no target we won't return path to the bucket,
    // instead we'll return empty string.
    if (empty($target)) {
      return '';
    }

    // Return external url.
    return $this->client->getBlobUrl($this->container, $target);
  }

  /**
   * Returns canonical, absolute path of the resource.
   *
   * Implementation placeholder. PHP's realpath() does not support stream
   * wrappers. We provide this as a default so that individual wrappers may
   * implement their own solutions.
   *
   * * This wrapper does not support realpath().
   *
   * @return bool
   *   Always returns FALSE.
   */
  public function realpath(): bool {
    return FALSE;
  }

  /**
   * Extract container name.
   *
   * @param string $path
   *   The path to get the container name.
   *
   * @return string
   *   The container name.
   */
  protected function getContainerName(string $path): string {
    $url = parse_url($path);
    if ($url['host']) {
      return $url['host'];
    }
    return '';
  }

  /**
   * Extract file name.
   *
   * @param string $path
   *   The path to get the file name.
   *
   * @return string
   *   The file name.
   */
  protected function getFileName(string $path): string {
    $url = parse_url($path);
    if ($url['host']) {
      $fileName = $url['path'] ?? $url['host'];
      if (strpos($fileName, '/') === 0) {
        $fileName = substr($fileName, 1);
      }
      return $fileName;
    }
    return '';
  }

  // @codingStandardsIgnoreStart
  /**
   * Close the directory listing handles.
   *
   * @return bool
   *   Returns TRUE if the operation was successful, FALSE otherwise.
   */
  // @codingStandardsIgnoreStart
  public function dir_closedir(): bool {
    // @codingStandardsIgnoreEnd
    $this->iterator = NULL;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Support for opendir().
   *
   * @param string $path
   *   The URI to the directory to open.
   * @param int $options
   *   A flag used to enable safe_mode.
   *   This wrapper doesn't support safe_mode, so this parameter is ignored.
   *
   * @return bool
   *   TRUE on success. Otherwise, FALSE.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-opendir.php
   */
  // @codingStandardsIgnoreStart
  public function dir_opendir($path, $options): bool {
    $uri = $path;

    // @codingStandardsIgnoreEnd
    if ($this->client->uriIsFile($uri)) {
      // Path is a file but return TRUE without creating the iterator.
      return TRUE;
    }

    $prefix = $uri;
    if ($uri == '/' || $uri == '') {
      $prefix = '';
    }
    else {
      // Add trailing slash.
      if (substr($uri, -1) != '/') {
        $prefix = $uri . '/';
      }
    }

    $options = new ListBlobsOptions();
    $options->setPrefix($prefix);
    $options->setDelimiter('/');
    $blobs_result = $this->client->listBlobs($this->container, $options);

    $blobs = new \ArrayObject($blobs_result->getBlobs());
    if (empty($blobs)) {
      $this->dir_closedir();
      return FALSE;
    }
    $this->iterator = $blobs->getIterator();

    return TRUE;
  }

  /**
   * This method is called in response to readdir()
   *
   * @return string
   *   Should return a string representing the next filename, or false if there
   *   is no next file.
   *
   * @link http://www.php.net/manual/en/function.readdir.php
   */
  // @codingStandardsIgnoreStart
  public function dir_readdir() {
    // @codingStandardsIgnoreEnd
    // Skip empty result keys.
    if (!$this->iterator->valid()) {
      return FALSE;
    }

    $file_name = $this->iterator->valid() ? $this->iterator->current()
      ->getName() : FALSE;
    $this->iterator->next();

    // The blobs hold their names as the full path of the namespace
    // we want the actual file name.
    if ($file_name) {
      $file_name = explode('/', $file_name);
      return end($file_name);
    }

    return FALSE;
  }

  /**
   * This method is called in response to rewinddir().
   *
   * @return bool
   *   Returns TRUE if the operation was successful, FALSE otherwise.
   */
  // @codingStandardsIgnoreStart
  public function dir_rewinddir(): bool {
    // @codingStandardsIgnoreEnd
    // If our iterator is empty, there is nothing to rewind.
    // This also means there is likely no directory.
    if ($this->iterator === NULL) {
      return FALSE;
    }

    // Try to rewind our iterator.
    try {
      $this->iterator->rewind();
      return TRUE;
    }
    catch (ServiceException $e) {
      watchdog_exception('Azure Blob Stream Exception (dir_rewinddir)', $e);
      return FALSE;
    }
  }

  /**
   * Azure Blob Storage doesn't support physical directories.
   *
   * So always return TRUE.
   *
   * @param string $path
   *   The path.
   * @param int $mode
   *   The mode.
   * @param int $options
   *   The options.
   *
   * @return bool
   *   Returns TRUE if the operation was successful, FALSE otherwise.
   */
  public function mkdir($path, $mode, $options): bool {
    return TRUE;
  }

  /**
   * Called in response to rename() to rename a file or directory.
   *
   * Currently, only supports renaming objects.
   *
   * In Azure Blob Storage, we have to copy the existing blob to a new one with
   * the new name, then delete the original.
   *
   * @param string $path_from
   *   The path to the file to rename.
   * @param string $path_to
   *   The new path to the file.
   *
   * @return bool
   *   True if file was successfully renamed
   *
   * @link http://www.php.net/manual/en/function.rename.php
   */
  public function rename($path_from, $path_to): bool {
    return $this->renameBlob($path_from, $path_to);
  }

  /**
   * Called in response to rmdir(). To remove a directory.
   *
   * {@inheritdoc}
   */
  public function rmdir($path, $options): bool {
    // Delete what is found at the provided URI.
    return $this->deleteRemotePath($path);
  }

  /**
   * Retrieve the underlying stream resource.
   *
   * This method is called in response to stream_select().
   *
   * @param int $cast_as
   *   Can be STREAM_CAST_FOR_SELECT when stream_select() is calling
   *   stream_cast() or STREAM_CAST_AS_STREAM when stream_cast() is called for
   *   other uses.
   *
   * @return resource|false
   *   The underlying stream resource or FALSE if stream_select() is not
   *   supported.
   *
   * @see stream_select()
   * @see http://php.net/manual/streamwrapper.stream-cast.php
   */
  // @codingStandardsIgnoreStart
  public function stream_cast($cast_as): bool {
    // @codingStandardsIgnoreEnd
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function stream_close() {
    // @codingStandardsIgnoreEnd
    $this->stream = $this->cache = NULL;
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function stream_eof(): bool {
    // @codingStandardsIgnoreEnd
    return $this->eof();
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function stream_flush(): bool {
    // @codingStandardsIgnoreEnd
    if ($this->mode == 'r') {
      return FALSE;
    }

    if ($this->isSeekable()) {
      $this->seek(0);
    }

    try {
      $blob_name = $this->streamWrapperManager->getTarget($this->uri);
      $blob_parts = pathinfo($blob_name);
      $blob_extension = $blob_parts['extension'] ?? NULL;

      $block_counter = 1;
      $blocks = [];
      while (!$this->stream->eof()) {
        // Make sure not to exceed max length for block id.
        $block_id = base64_encode(md5(basename($this->uri) . $block_counter));

        // Create block object.
        $block = new Block($block_id);
        $block->setType('Uncommitted');

        // Add to blocks array.
        $blocks[] = $block;

        // Get block chunk size.
        $block_size = $this->client->getBlockSize();
        if (empty($block_size)) {
          $block_size = Resources::MB_IN_BYTES_100;
        }

        // Get chunked data to store in block.
        $block_data = $this->stream->read($block_size);
        $this->client->createBlobBlock($this->container, $blob_name, $block_id, $block_data);

        // Increment block counter.
        $block_counter++;
      }

      // Set block options.
      $options = new CommitBlobBlocksOptions();

      // Set the content type.
      $content_types = $this->getContentTypes();
      if (!empty($content_types[$blob_extension])) {
        $options->setContentType($content_types[$blob_extension]);
      }

      // Commit blocks.
      $blocksList = BlockList::create($blocks);
      $this->client->commitBlobBlocks($this->container, $blob_name, $blocksList, $options);

      $this->stream = '';
      $this->iterator = FALSE;

      return TRUE;
    }
    catch (ServiceException $e) {
      watchdog_exception('Azure Blob Stream Exception (stream_flush)', $e);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function stream_lock($operation): bool {
    // @codingStandardsIgnoreEnd
    return FALSE;
  }

  /**
   * Sets metadata on the stream.
   *
   * @param string $path
   *   A string containing the URI to the file to set metadata on.
   * @param int $option
   *   One of:
   *   - STREAM_META_TOUCH: The method was called in response to touch().
   *   - STREAM_META_OWNER_NAME: The method was called in response to chown()
   *     with string parameter.
   *   - STREAM_META_OWNER: The method was called in response to chown().
   *   - STREAM_META_GROUP_NAME: The method was called in response to chgrp().
   *   - STREAM_META_GROUP: The method was called in response to chgrp().
   *   - STREAM_META_ACCESS: The method was called in response to chmod().
   * @param mixed $value
   *   If option is:
   *   - STREAM_META_TOUCH: Array consisting of two arguments of the touch()
   *     function.
   *   - STREAM_META_OWNER_NAME or STREAM_META_GROUP_NAME: The name of the owner
   *     user/group as string.
   *   - STREAM_META_OWNER or STREAM_META_GROUP: The value of the owner
   *     user/group as integer.
   *   - STREAM_META_ACCESS: The argument of the chmod() as integer.
   *
   *    * This wrapper does not support touch(), chmod(), chown(), or chgrp().
   *
   * @return bool
   *   Manual recommends return FALSE for not implemented options, but Drupal
   *   require TRUE in some cases like chmod for avoid watchdog erros.
   *
   * @see \Drupal\Core\File\FileSystem::chmod()
   *
   * Returns FALSE if the option is not included in bypassed_options array
   * otherwise, TRUE.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-metadata.php
   * @see http://php.net/manual/streamwrapper.stream-metadata.php
   */
  // @codingStandardsIgnoreStart
  public function stream_metadata($path, $option, $value): bool {
    // @codingStandardsIgnoreEnd
    $bypassed_options = [STREAM_META_ACCESS];
    return in_array($option, $bypassed_options);
  }

  /**
   * Opens a stream, as for fopen(), file_get_contents(), file_put_contents().
   *
   * @param string $path
   *   A string containing the URI to the file to open.
   * @param string $mode
   *   The file mode ("r", "wb" etc.).
   * @param int $options
   *   A bit mask of STREAM_USE_PATH and STREAM_REPORT_ERRORS.
   * @param string &$opened_path
   *   A string containing the path actually opened.
   *
   * @return bool
   *   Returns TRUE if file was opened successfully. (Always returns TRUE).
   *
   * @see http://php.net/manual/en/streamwrapper.stream-open.php
   */
  // @codingStandardsIgnoreStart
  public function stream_open($path, $mode, $options, &$opened_path): bool {
    $uri = $path;

    // @codingStandardsIgnoreEnd
    if (!$this->isClientReady()) {
      return FALSE;
    }

    $this->setUri($uri);
    $this->stream = new Stream(fopen('php://temp', $mode));

    if (in_array($mode, [
      'r',
      'rb',
      'rt',
    ])) {
      // Get the target destination without the scheme.
      $target = $this->streamWrapperManager->getTarget($this->uri);
      try {
        $blob = $this->client->getBlob($this->container, $target);
        $this->temporaryFileHandle = $blob->getContentStream();
      }
      catch (ServiceException $e) {
        watchdog_exception('Azure Blob Stream Exception (stream_open)', $e);
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function stream_read($count) {
    // @codingStandardsIgnoreEnd
    return fread($this->temporaryFileHandle, $count);
  }

  /**
   * Seeks to specific location in a stream.
   *
   * This method is called in response to fseek().
   *
   * The read/write position of the stream should be updated according to the
   * offset and whence.
   *
   * @param int $offset
   *   The byte offset to seek to.
   * @param int $whence
   *   Possible values:
   *   - SEEK_SET: Set position equal to offset bytes.
   *   - SEEK_CUR: Set position to current location plus offset.
   *   - SEEK_END: Set position to end-of-file plus offset.
   *   Defaults to SEEK_SET.
   *
   * @return bool
   *   TRUE if the position was updated, FALSE otherwise.
   *
   * @see http://php.net/manual/streamwrapper.stream-seek.php
   */
  // @codingStandardsIgnoreStart
  public function stream_seek($offset, $whence = SEEK_SET): bool {
    // @codingStandardsIgnoreEnd
    return !fseek($this->temporaryFileHandle, $offset, $whence);
  }

  /**
   * Change stream options.
   *
   * This method is called to set options on the stream.
   *
   * @param int $option
   *   One of:
   *   - STREAM_OPTION_BLOCKING: The method was called in response to
   *     stream_set_blocking().
   *   - STREAM_OPTION_READ_TIMEOUT: The method was called in response to
   *     stream_set_timeout().
   *   - STREAM_OPTION_WRITE_BUFFER: The method was called in response to
   *     stream_set_write_buffer().
   * @param int $arg1
   *   If option is:
   *   - STREAM_OPTION_BLOCKING: The requested blocking mode:
   *     - 1 means blocking.
   *     - 0 means not blocking.
   *   - STREAM_OPTION_READ_TIMEOUT: The timeout in seconds.
   *   - STREAM_OPTION_WRITE_BUFFER: The buffer mode, STREAM_BUFFER_NONE or
   *     STREAM_BUFFER_FULL.
   * @param int $arg2
   *   If option is:
   *   - STREAM_OPTION_BLOCKING: This option is not set.
   *   - STREAM_OPTION_READ_TIMEOUT: The timeout in microseconds.
   *   - STREAM_OPTION_WRITE_BUFFER: The requested buffer size.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise. If $option is not implemented, FALSE
   *   should be returned.
   */
  // @codingStandardsIgnoreStart
  public function stream_set_option($option, $arg1, $arg2): bool {
    // @codingStandardsIgnoreEnd
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function stream_stat() {
    // @codingStandardsIgnoreEnd
    return $this->url_stat($this->uri, 0);
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function stream_tell(): int {
    // @codingStandardsIgnoreEnd
    return $this->tell();
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function stream_truncate($new_size): bool {
    // @codingStandardsIgnoreEnd
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function stream_write($data): int {
    // @codingStandardsIgnoreEnd
    return $this->write($data);
  }

  /**
   * Support for unlink().
   *
   * @param string $path
   *   A string containing the uri to the resource to delete.
   *
   * @return bool
   *   Returns the deleted remote path.
   *
   * @see http://php.net/manual/en/streamwrapper.unlink.php
   */
  // @codingStandardsIgnoreStart
  public function unlink($path): bool {
    // @codingStandardsIgnoreEnd
    // Delete what is found at the provided URI.
    return $this->deleteRemotePath($path);
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  public function url_stat($path, $flags) {
    $uri = $path;

    // @codingStandardsIgnoreEnd
    if (!$this->isClientReady()) {
      return FALSE;
    }

    // @see http://be2.php.net/manual/en/function.stat.php
    $stat = array_fill_keys([
      'dev',
      'ino',
      'mode',
      'nlink',
      'uid',
      'gid',
      'rdev',
      'size',
      'atime',
      'mtime',
      'ctime',
      'blksize',
      'blocks',
    ], 0);

    // If $blob_prefixes is not empty and $blobs is, it means it's a directory.
    // If $blobs is not empty and $blob_prefixes is, it's a file.
    // If both are empty, the blob does not exist.
    // Get the target destination without the scheme.
    $target = $this->streamWrapperManager->getTarget($uri);
    try {
      $blob = $this->client->getBlobProperties($this->container, $target);
    }
    catch (ServiceException $e) {
      // If it is a 404 code, continue on, otherwise, throw the exception..
      if ($e->getCode() !== 404) {
        watchdog_exception('Azure Blob Stream Exception (url_stat)', $e);
        return FALSE;
      }
    }

    $blob_prefixes = [];
    $pathArray = explode("/", $target);
    $count = 0;
    foreach ($pathArray as $slice) {
      $pos = strpos($slice, '.');
      $count++;
      if ($pos !== FALSE) {
        $blob_prefixes[] = $slice;
      }
    }

    // Blob exists.
    // Blob is a file.
    if (!empty($blob) && !empty($blob_prefixes)) {
      $blob_properties = $blob->getProperties();

      // Use the S_IFREG posix flag for files.
      // All files are considered writable, so OR in 0777.
      $stat['mode'] = 0100000 | 0777;
      $stat['size'] = $blob_properties->getContentLength();
      $stat['mtime'] = date_timestamp_get($blob_properties->getLastModified());
      $stat['blksize'] = -1;
      $stat['blocks'] = -1;
    }

    if (empty($blob)) {
      // Blob is directory.
      if (empty($blob_prefixes)) {
        // Use the S_IFDIR posix flag for directories
        // All directories are considered writable, so OR in 0777.
        $stat['mode'] = 0040000 | 0777;
      }
      else {
        $stat = FALSE;
      }
    }

    return $stat;
  }

  /**
   * Gets the name of the directory from a given path.
   *
   * This method is usually accessed through drupal_dirname(), which wraps
   * around the normal PHP dirname() function, which does not support stream
   * wrappers.
   *
   * @param string $uri
   *   An optional URI.
   *
   * @return string
   *   A string containing the directory name, or FALSE if not applicable.
   *
   * @see \Drupal::service('file_system')->dirname()
   */
  public function dirname($uri = NULL): string {
    if (!isset($uri)) {
      $uri = $this->uri;
    }

    // Get scheme.
    $scheme = StreamWrapperManager::getScheme($uri);

    // Get directory name.
    $dirname = $this->fileSystem->dirname(($this->streamWrapperManager->getTarget($uri)));

    // When the dirname() call above is given '$scheme://', it returns '.'.
    // But '$scheme://.' is an invalid uri, so we return "$scheme://" instead.
    if ($dirname == '.') {
      $dirname = '';
    }

    return "$scheme://$dirname";
  }

}
