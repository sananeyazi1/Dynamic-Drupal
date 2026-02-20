<?php

namespace Drupal\scorm_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\node\NodeInterface;

/**
 * Plugin implementation of the 'scorm_field_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "scorm_field_field_formatter",
 *   label = @Translation("Social Course Scorm player"),
 *   field_types = {
 *     "scorm_field_package"
 *   }
 * )
 */
class ScormFieldFieldFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $nid = NULL;
    
    // Read the entity id from url
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      $nid = $node->id();
    }

    $scorm_service = \Drupal::service('scorm_field.scorm');
    $scorm_player = \Drupal::service('scorm_field.scorm_player');
    $first = TRUE;
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      if ($first) {
        $scorm = $scorm_service->scormLoadByFileEntity($file);
        $elements[$delta] = $scorm_player->toRendarableArray($scorm, $nid);
        $first = FALSE;
      }
      else {
        $elements[$delta] = [
          '#markup' => $this->t("As per <a href='!link' target='_blank'>SCORM.2004.3ED.ConfReq.v1.0</a>, only <em>only one SCO can be launched at a time.</em> To enforce this, only one SCORM package is loaded inside the player on this page at a time.", ['!link' => 'http://www.adlnet.gov/wp-content/uploads/2011/07/SCORM.2004.3ED.ConfReq.v1.0.pdf']),
        ];
      }
    }

    return $elements;
  }

}
