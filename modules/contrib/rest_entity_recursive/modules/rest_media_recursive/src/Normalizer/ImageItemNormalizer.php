<?php

namespace Drupal\rest_media_recursive\Normalizer;

use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\rest_entity_recursive\Normalizer\ReferenceItemNormalizer;

/**
 * Class ImageItemNormalizer.
 *
 * @package Drupal\rest_media_recursive\Normalizer
 */
class ImageItemNormalizer extends ReferenceItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ImageItem::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []): float|array|\ArrayObject|bool|int|string|null {
    return parent::normalize($data, $format, $context) + [
        'title' => $data->get('title')->getValue(),
        'alt' =>  $data->get('alt')->getValue(),
      ];
  }

}
