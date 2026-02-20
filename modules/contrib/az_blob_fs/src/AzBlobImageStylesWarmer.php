<?php

namespace Drupal\az_blob_fs;

use Drupal\az_blob_fs\Constants\AzBlobFsConstants;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Queue\QueueFactory;
use Drupal\file\FileInterface;

/**
 * Defines an Azure Blob images styles warmer.
 */
class AzBlobImageStylesWarmer implements AzBlobImageStylesWarmerInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The file entity storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $file;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $image;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  protected $imageStyles;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Constructs a AzBlobImageStylesWarmer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $file_storage
   *   The file storage.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $file_storage, ImageFactory $image_factory, EntityTypeManagerInterface $image_style_storage, QueueFactory $queue_factory) {
    $this->config = $config_factory->get('az_blob_fs.settings');
    $this->file = $file_storage->getStorage('file');
    $this->image = $image_factory;
    $this->imageStyles = $image_style_storage->getStorage('image_style');
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function warmUp(FileInterface $file) {
    $initialImageStyles = $this->config->get('az_blob_initial_image_styles');
    if (!empty($initialImageStyles)) {
      $this->doWarmUp($file, array_keys($initialImageStyles));
    }
    $queueImageStyles = $this->config->get('az_blob_queue_image_styles');
    if (!empty($queueImageStyles)) {
      $this->addQueue($file, array_keys($queueImageStyles));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function doWarmUp(FileInterface $file, array $image_styles) {
    if (empty($image_styles) || !$this->validateImage($file)) {
      return;
    }

    /** @var \Drupal\Core\Image\Image $image */
    /** @var \Drupal\image\Entity\ImageStyle $style */

    // Create image derivatives if they not already exists.
    $styles = $this->imageStyles->loadMultiple($image_styles);
    $image_uri = $file->getFileUri();
    foreach ($styles as $style) {
      $derivative_uri = $style->buildUri($image_uri);
      if (!file_exists($derivative_uri)) {
        $style->createDerivative($image_uri, $derivative_uri);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addQueue(FileInterface $file, array $image_styles) {
    if (!empty($image_styles) && $this->validateImage($file)) {
      $queue = $this->queueFactory->get('az_blob_fs_images_pregenerator');
      $data = ['file_id' => $file->id(), 'image_styles' => $image_styles];
      $queue->createItem($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateImage(FileInterface $file): bool {
    // Make sure file is permanent and is using Azure blob scheme.
    if ($file->isPermanent() && strpos($file->getFileUri(), AzBlobFsConstants::SCHEME, 0) !== FALSE) {
      $image = $this->image->get($file->getFileUri());
      $extensions = implode(' ', $image->getToolkit()->getSupportedExtensions());
      // Make sure we have valid image.
      if ($image->isValid() && empty(file_validate_extensions($file, $extensions))) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
