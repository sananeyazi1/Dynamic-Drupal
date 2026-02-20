<?php

namespace Drupal\rest_menu_recursive\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\rest_entity_recursive\Normalizer\ContentEntityNormalizer;

/**
 * Menu normalizer.
 */
class MenuNormalizer extends ContentEntityNormalizer {

  protected $supportedInterfaceOrClass = 'Drupal\system\MenuInterface';

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * Maximum depth of the loaded menu tree.
   */
  const MENU_MAX_DEPTH = 4;

  /**
   * Constructs a new SystemController.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, MenuLinkTreeInterface $menu_link_tree, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityRepository = $entity_repository;
    $this->menuLinkTree = $menu_link_tree;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $links = [];

    // Load menu tree and pass to normalizer.
    $parameters = new MenuTreeParameters();
    $parameters->setMaxDepth(self::MENU_MAX_DEPTH)->onlyEnabledLinks();
    $tree = $this->menuLinkTree->load($entity->id(), $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    foreach ($tree as $element) {
      // Forbidden links are still represented in the tree. Filter them out.
      if ($element->access->isAllowed()) {
        $links[] = $this->normalizeLink($element, $format, $context);
      }
    }

    // Rendering the whole tree is the simplest way to gather all cache tags
    // and contexts recursively.
    $build = $this->menuLinkTree->build($tree);
    // Transform rendered menu into cacheable dependency.
    $this->addCacheableDependency($context, CacheableMetadata::createFromRenderArray($build));

    return [
      'id' => $entity->id(),
      'label' => $entity->label(),
      'links' => $links,
    ];
  }

  /**
   * Normalizes menu link with its children.
   */
  protected function normalizeLink(MenuLinkTreeElement $linkPlugin, $format, array $context) {
    /* @var $link \Drupal\menu_link_content\Entity\MenuLinkContent */
    $link = $this->entityRepository->loadEntityByUuid('menu_link_content', $linkPlugin->link->getDerivativeId());

    // Expose basic link data.
    $link_normalized = [
      'id' => $link->id(),
      'uuid' => $link->uuid(),
      'depth' => $linkPlugin->depth,
      'title' => $link->getTitle(),
      'url' => $this->serializer->normalize($link->getUrlObject(), $format, $context),
      'children' => [],
    ];

    // Expose any user-defined fields.
    /* @var $fields \Drupal\Core\Field\FieldDefinitionInterface[] */
    $fields = $this->entityFieldManager->getFieldDefinitions($link->getEntityTypeId(), $link->bundle());
    foreach ($fields as $field_definition) {
      $field_name = $field_definition->getName();
      // Excluding BaseFieldDefinition implementations because we are not
      // interested in base fields of menu links.
      if (!($field_definition instanceof BaseFieldDefinition)
        && $link->get($field_name)->access('view')
        && !$link->get($field_name)->isEmpty()) {
        $link_normalized[$field_name] = $this->serializer->normalize($link->get($field_name), $format, $context);
      }
    }

    // Recursively process all children menu links.
    if ($linkPlugin->hasChildren) {
      foreach ($linkPlugin->subtree as $element) {
        if ($element->access->isAllowed()) {
          $link_normalized['children'][] = $this->normalizeLink($element, $format, $context);
        }
      }
    }

    return $link_normalized;
  }

}
