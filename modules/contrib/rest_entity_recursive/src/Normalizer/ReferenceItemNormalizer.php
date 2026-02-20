<?php

namespace Drupal\rest_entity_recursive\Normalizer;

use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;

/**
 * Class ReferenceItemNormalizer.
 *
 * @package Drupal\rest_entity_recursive\Normalizer
 */
class ReferenceItemNormalizer extends EntityReferenceFieldItemNormalizer {

  /**
   * The format that the Normalizer can handle.
   *
   * @var array
   */
  protected $format = ['json_recursive'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null {
    // Check current depth. Not include entity if it is max depth.
    if ($context['current_depth'] === $context['max_depth']) {
      return parent::normalize($field_item, $format, $context);
    }
    // Increase current depth.
    $context['current_depth']++;

    /* @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $field_item->get('entity')->getValue();

    if (empty($entity)) {
      return parent::normalize($field_item, $format, $context);
    }

    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository */
    $entityRepository = \Drupal::service('entity.repository');
    $entity = $entityRepository->getTranslationFromContext($entity);

    // Add a cacheable dependency before the access check to be able to react
    // to publish/unpublish operations with cache invalidation.
    $this->addCacheableDependency($context, $entity);

    // Make sure a user has access to view the referenced entity.
    // Also make sure that loading of this type of entity is not forbidden in
    // settings.
    if (empty($entity) || !$entity->access('view') || !empty($context['settings'][$entity->getEntityTypeId()]['disable'])) {
      return parent::normalize($field_item, $format, $context);
    }

    return $this->serializer->normalize($entity, $format, $context);
  }

}
