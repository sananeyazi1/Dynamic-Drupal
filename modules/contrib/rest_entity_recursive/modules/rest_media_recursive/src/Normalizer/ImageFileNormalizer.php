<?php

namespace Drupal\rest_media_recursive\Normalizer;

use Drupal\consumer_image_styles\ImageStylesProvider;
use Drupal\consumers\Negotiator;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGenerator;

/**
 * Class ImageFileNormalizer.
 *
 * Normalizer adds image styles for image.
 *
 * @package Drupal\rest_media_recursive\Normalizer
 */
class ImageFileNormalizer extends FileNormalizer {

  /**
   * Consumer negotiator.
   *
   * @var \Drupal\consumers\Negotiator
   */
  protected $consumerNegotiator;

  /**
   * Image style provider.
   *
   * @var \Drupal\consumer_image_styles\ImageStylesProvider
   */
  protected $imageStylesProvider;

  /**
   * File URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * Constructs an ImageItemNormalizer object.
   *
   * @param \Drupal\consumers\Negotiator $consumer_negotiator
   *   The consumer negotiator.
   * @param \Drupal\consumer_image_styles\ImageStylesProvider $imageStylesProvider
   *   Image styles utility.
   * @param \Drupal\Core\File\FileUrlGenerator $file_url_generator
   *   File URL generator.
   */
  public function __construct(Negotiator $consumer_negotiator, ImageStylesProvider $imageStylesProvider, FileUrlGenerator $file_url_generator) {
    $this->consumerNegotiator = $consumer_negotiator;
    $this->imageStylesProvider = $imageStylesProvider;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, string $format = NULL, array $context = []): bool {
    return parent::supportsNormalization($data, $format, $context) &&
      strpos($data->get('filemime')->value, 'image/') !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    $normalized_values = parent::normalize($data, $format, $context);
    $normalized_values['image_styles'] = $this->buildVariantValues($data);

    return $normalized_values;
  }

  /**
   * Creates array of image styles for image.
   * @see \Drupal\consumer_image_styles\Normalizer\ImageEntityNormalizer::buildVariantValues()
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   *
   * @return array|false
   */
  protected function buildVariantValues(EntityInterface $entity) {
    $consumer = $this->consumerNegotiator->negotiateFromRequest();

    // If consumer not found return empty array.
    if (!$consumer) {
      return [];
    }

    // Prepare some utils.
    $uri = $entity->getFileUri();
    $get_image_url = function ($image_style) use ($uri) {
      return $this->fileUrlGenerator->transformRelative($image_style->buildUrl($uri));
    };

    // Generate derivatives only for the found ones.
    $image_styles = $this->imageStylesProvider->loadStyles($consumer);
    $keys = array_keys($image_styles);
    $values = array_map($get_image_url, array_values($image_styles));
    $result = array_combine($keys, $values);

    // Add original url to array.
    $result['original'] = $this->fileUrlGenerator->generateString($uri);

    return $result;
  }

}
