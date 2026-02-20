<?php

namespace Drupal\scorm_field\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Returns responses for Scorm field routes.
 */
class ScormFieldDecoupledController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user. 
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $account) {
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Builds the response.
   */
  public function buildScormPlayer($node) {

    $build['body'] = ['#markup' => 'No scorm.'];

    if ($node instanceof NodeInterface) {
      $field_definitions = $node->getFieldDefinitions();
      foreach ($field_definitions as $machine_name => $field) {
        $className = $field->getClass();
        if ($className === '\Drupal\scorm_field\Plugin\Field\FieldType\ScormFieldScormPackageItemList') {
          $existingData = $node->$machine_name->referencedEntities();
          if(!empty($existingData)) {
            $build['body'] = $node->$machine_name->view('decoupled');
          }
          else {
            $build['body'] = ['#markup' => 'Empty scorm field.'];
          }
        }        
      }
    }

    return $build;
  
  }

  /**
   * Returns a page title.
   */
  public function getTitle($node) {
    if ($node instanceof NodeInterface) {
      return  $node->getTitle();
    }
  }  

}
