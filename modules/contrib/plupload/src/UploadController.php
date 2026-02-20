<?php

namespace Drupal\plupload;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\HtaccessWriterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plupload upload handling route.
 */
class UploadController implements ContainerInjectionInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   *   The HTTP request object.
   */
  protected $request;

  /**
   * Stores temporary folder URI.
   *
   * This is configurable via the configuration variable. It was added for HA
   * environments where temporary location may need to be a shared across all
   * servers.
   *
   * @var string
   */
  protected $temporaryUploadLocation;

  /**
   * Filename of a file that is being uploaded.
   *
   * @var string
   */
  protected $filename;

  /**
   * HTAccess writer service.
   *
   * @var \Drupal\Core\File\HtaccessWriterInterface
   */
  protected $htaccessWriter;

  /**
   * File System service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs plupload upload controller route controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   * @param \Drupal\Core\File\HtaccessWriterInterface $htaccess_writer
   *   HTAccess writer service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   File System service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(Request $request, HtaccessWriterInterface $htaccess_writer, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory) {
    $this->request = $request;
    $this->temporaryUploadLocation = $config_factory->get('plupload.settings')->get('temporary_uri');
    $this->htaccessWriter = $htaccess_writer;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('file.htaccess_writer'),
      $container->get('file_system'),
      $container->get('config.factory')
    );
  }

  /**
   * Handles Plupload uploads.
   */
  public function handleUploads() {
    // @todo Implement file_validate_size();
    try {
      $this->prepareTemporaryUploadDestination();
      $this->handleUpload();
    }
    catch (UploadException $e) {
      return $e->getErrorResponse();
    }

    // Return JSON-RPC response.
    return new JsonResponse(
      [
        'jsonrpc' => '2.0',
        'result' => NULL,
        'id' => 'id',
      ],
      200
    );
  }

  /**
   * Prepares temporary destination folder for uploaded files.
   *
   * @throws \Drupal\plupload\UploadException
   */
  protected function prepareTemporaryUploadDestination() {
    $writable = $this->fileSystem->prepareDirectory($this->temporaryUploadLocation, FileSystemInterface::CREATE_DIRECTORY);
    if (!$writable) {
      throw new UploadException(UploadException::DESTINATION_FOLDER_ERROR);
    }

    // Try to make sure this is private via htaccess.
    $this->htaccessWriter->write($this->temporaryUploadLocation, TRUE);
  }

  /**
   * Reads, checks and return filename of a file being uploaded.
   *
   * @throws \Drupal\plupload\UploadException
   */
  protected function getFilename() {
    if (empty($this->filename)) {
      try {
        // @todo this should probably bo OO.
        $this->filename = _plupload_fix_temporary_filename($this->request->request->get('name'));
      }
      catch (InvalidArgumentException $e) {
        throw new UploadException(UploadException::FILENAME_ERROR);
      }

      // Check the file name for security reasons; it must contain letters,
      // numbers and underscores followed by a (single) ".tmp" extension.
      // Since this check is more stringent than the one performed in
      // plupload_element_value(), we do not need to run the checks performed
      // in that function here. This is fortunate, because it would be
      // difficult for us to get the correct list of allowed extensions
      // to pass in to file_munge_filename() from this point in the code
      // (outside the form API).
      if (!preg_match('/^\w+\.tmp$/', $this->filename)) {
        throw new UploadException(UploadException::FILENAME_ERROR);
      }
    }

    return $this->filename;
  }

  /**
   * Handles multipart uploads.
   *
   * @throws \Drupal\plupload\UploadException
   */
  protected function handleUpload() {
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $multipart_file */
    $is_multipart = strpos($this->request->headers->get('Content-Type'), 'multipart') !== FALSE;

    // If this is a multipart upload there needs to be a file on the server.
    if ($is_multipart) {
      $multipart_file = $this->request->files->get('file', []);
      // @todo Not sure if this is the best check now.
      // Originally it was:
      // if (empty($multipart_file['tmp_name']) ||
      // !is_uploaded_file($multipart_file['tmp_name'])) {.
      if (!$multipart_file->getPathname() || !$this->isUploadedFile($multipart_file->getPathname())) {
        throw new UploadException(UploadException::MOVE_ERROR);
      }
    }

    // Open temp file.
    if (!($out = fopen($this->temporaryUploadLocation . $this->getFilename(), $this->request->request->get('chunk', 0) ? 'ab' : 'wb'))) {
      throw new UploadException(UploadException::OUTPUT_ERROR);
    }

    // Read binary input stream.
    $input_uri = $is_multipart ? $multipart_file->getRealPath() : 'php://input';
    if (!($in = fopen($input_uri, 'rb'))) {
      throw new UploadException(UploadException::INPUT_ERROR);
    }

    // Append input stream to temp file.
    while ($buff = fread($in, 4096)) {
      fwrite($out, $buff);
    }

    // Be nice and keep everything nice and clean.
    fclose($in);
    fclose($out);
    if ($is_multipart) {
      $this->fileSystem->unlink($multipart_file->getRealPath());
    }
  }

  /**
   * Check if passed URI is an uploaded file.
   *
   * @param string $filename
   *   The URI to check.
   *
   * @return bool
   *   Whether the URI is an uploaded file.
   */
  protected function isUploadedFile(string $filename): bool {
    return is_uploaded_file($filename);
  }

}
