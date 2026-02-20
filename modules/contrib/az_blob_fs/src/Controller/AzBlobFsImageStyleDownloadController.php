<?php

namespace Drupal\az_blob_fs\Controller;

use Drupal\az_blob_fs\AzBlobFsService;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\image\Controller\ImageStyleDownloadController;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Defines a controller to serve Azure Blob File System image styles.
 */
class AzBlobFsImageStyleDownloadController extends ImageStyleDownloadController {

  /**
   * Azure Blob file system information.
   *
   * @var \Drupal\az_blob_fs\AzBlobFsService
   */
  protected $azBlobFs;

  /**
   * Constructs an AzBlobFsImageStyleDownloadController object.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\Core\File\FileSystemInterface|null $file_system
   *   The system service.
   * @param \Drupal\az_blob_fs\AzBlobFsService $azBlobFs
   *   The azBlobFsService class.
   */
  public function __construct(LockBackendInterface $lock, ImageFactory $image_factory, StreamWrapperManagerInterface $stream_wrapper_manager, FileSystemInterface $file_system = NULL, AzBlobFsService $azBlobFs) {
    parent::__construct($lock, $image_factory, $stream_wrapper_manager, $file_system);
    $this->azBlobFs = $azBlobFs;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lock'),
      $container->get('image.factory'),
      $container->get('stream_wrapper_manager'),
      $container->get('file_system'),
      $container->get('az_blob_fs')
    );
  }

  /**
   * Generates a derivative, given a style and image path.
   *
   * After generating an image, redirect it to the requesting agent. Only used
   * for azblob schemes. Private scheme use the normal workflow:
   * \Drupal\image\Controller\ImageStyleDownloadController::deliver().
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $scheme
   *   The file scheme.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to deliver.
   * @param string $required_derivative_scheme
   *   The required derivative scheme.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The redirect response or some error response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Invalid plugin exception.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Plugin not found exception.
   *
   * @see \Drupal\image\Controller\ImageStyleDownloadController::deliver()
   */
  public function deliver(Request $request, $scheme, ImageStyleInterface $image_style, string $required_derivative_scheme) {
    $target = $request->query->get('file');
    $image_uri = $scheme . '://' . $target;
    $headers = [];

    // Check that the style is defined, the scheme is valid, and the image
    // derivative token is valid. Sites which require image derivatives to be
    // generated without a token can set the
    // 'image.settings:allow_insecure_derivatives' configuration to TRUE to
    // bypass the latter check, but this will increase the site's vulnerability
    // to denial-of-service attacks. To prevent this variable from leaving the
    // site vulnerable to the most serious attacks, a token is always required
    // when a derivative of a style is requested.
    // The $target variable for a derivative of a style has
    // styles/<style_name>/... as structure, so we check if the $target variable
    // starts with styles/.
    $valid = !empty($image_style) && $this->streamWrapperManager->isValidScheme($scheme);
    if (!$this->config('image.settings')->get('allow_insecure_derivatives') || strpos(ltrim($target, '\/'), 'styles/') === 0) {
      $valid &= hash_equals($request->query->get(IMAGE_DERIVATIVE_TOKEN), $image_style->getPathToken($image_uri));
    }
    if (!$valid) {
      throw new AccessDeniedHttpException();
    }

    $derivative_uri = $image_style->buildUri($image_uri);
    $derivative_scheme = $this->streamWrapperManager->getScheme($derivative_uri);

    if ($required_derivative_scheme !== $derivative_scheme) {
      throw new AccessDeniedHttpException("The scheme for this image doesn't match the scheme for the original image");
    }
    // Private scheme use:
    // \Drupal\image\Controller\ImageStyleDownloadController::deliver()
    // instead of this.
    if ($scheme == 'private') {
      throw new AccessDeniedHttpException();
    }

    // Don't try to generate file if source is missing.
    if (!file_exists($image_uri)) {
      // If the image style converted the extension, it has been added to the
      // original file, resulting in filenames like image.png.jpeg. So to find
      // the actual source image, we remove the extension and check if that
      // image exists.
      $path_info = pathinfo($image_uri);
      $converted_image_uri = $path_info['dirname'] . DIRECTORY_SEPARATOR . $path_info['filename'];
      if (!file_exists($converted_image_uri)) {
        $this->logger->notice('Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.',
          [
            '%source_image_path' => $image_uri,
            '%derivative_path' => $derivative_uri,
          ]
        );
        return new Response($this->t('Error generating image, missing source file.'), 404);
      }
      else {
        // The converted file does exist, use it as the source.
        $image_uri = $converted_image_uri;
      }
    }

    // Don't start generating the image if the derivative already exists or if
    // generation is in progress in another thread.
    if (!file_exists($derivative_uri)) {
      $lock_name = 'az_blob_fs_image_style_deliver:' . $image_style->id() . ':' . Crypt::hashBase64($image_uri);
      $lock_acquired = $this->lock->acquire($lock_name);
      if (!$lock_acquired) {
        // Tell client to retry again in 3 seconds. Currently no browsers are
        // known to support Retry-After.
        throw new ServiceUnavailableHttpException(3, 'Image generation in progress. Try again shortly.');
      }
    }

    // Try to generate the image, unless another thread just did it while we
    // were acquiring the lock.
    $success = file_exists($derivative_uri) || $image_style->createDerivative($image_uri, $derivative_uri);

    if (!empty($lock_acquired)) {
      /* @noinspection PhpUndefinedVariableInspection */
      $this->lock->release($lock_name);
    }

    // On Success.
    if ($success) {
      // Try to find and invalidate the file entity cache.
      /** @var \Drupal\file\FileInterface[] $files */
      $files = $this->entityTypeManager()
        ->getStorage('file')
        ->loadByProperties([
          'uri' => $image_uri,
        ]);
      if (!empty($files)) {
        $file = reset($files);
        Cache::invalidateTags($file->getCacheTags());
      }

      $image = $this->imageFactory->get($derivative_uri);
      $uri = $image->getSource();
      $headers += [
        'Content-Type' => $image->getMimeType(),
        'Content-Length' => $image->getFileSize(),
      ];
      // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
      // sets response as not cacheable if the Cache-Control header is not
      // already modified. We pass in FALSE for non-private schemes for the
      // $public parameter to make sure we don't change the headers.
      return new BinaryFileResponse($uri, 200, $headers, $scheme !== 'private');
    }

    $this->logger->notice('Unable to generate the derived image located at %path.', ['%path' => $derivative_uri]);
    return new Response($this->t('Error generating image.'), 500);
  }

}
