<?php

namespace Drupal\scorm_field\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;

/**
 * Checks access for display rss feeds for ical views
 */
class ScormFieldReportViewsAccessCheck implements AccessInterface {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {

    $access = AccessResult::forbidden();

    $node = \Drupal::routeMatch()->getParameter('node');

    if (isset($node) && !is_object($node)) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($node);
    }

    if ($node instanceof NodeInterface) {

      $field_definitions = $node->getFieldDefinitions();
      foreach ($field_definitions as $machine_name => $field) {
        $className = $field->getClass();
        if ($className === '\Drupal\scorm_field\Plugin\Field\FieldType\ScormFieldScormPackageItemList') {
          if ($node->access('update', $account)) {
            $access = AccessResult::allowed();
          }
        }        
      }
    }
    
    return $access;

  }

}

