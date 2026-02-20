<?php

namespace Drupal\scorm_field;

use Drupal\Core\Database\Connection;
use Drupal\scorm_field\ScormFieldScorm;

/**
 * Class ScormFieldScormPlayer.
 */
class ScormFieldScormPlayer {

  protected $database;

  protected $scorm_service;

  /**
   * ScormFieldScormPlayer constructor.
   */
  public function __construct(Connection $database, ScormFieldScorm $scorm_service) {
    $this->database = $database;
    $this->scorm_service = $scorm_service;
  }

  /**
   * Build rendarable array for scorm package output.
   */
  public function toRendarableArray($scorm, $nid) {

    $decoupled_mode = FALSE;

    $account = \Drupal::currentUser();
    // Get SCORM API version.
    $metadata = unserialize($scorm->metadata);
    if (strpos($metadata['schemaversion'], '1.2') !== FALSE) {
      $scorm_version = '1.2';
    }
    else {
      $scorm_version = '2004';
    }

    // Get the SCO tree.
    $tree = $this->scormFieldScormPlayerScormTree($scorm);
    $flat_tree = $this->scormFieldScormPlayerFlattenTree($tree);

    // Get the start SCO.
    $start_sco = $this->scormFieldScormPlayerStartSco($flat_tree);

    /* @todo Replace with custom event subscriber implementation. */
    // Get implemented CMI paths.
    $paths = scorm_field_scorm_add_cmi_paths($scorm_version);

    // Get CMI data for each SCO.
    $data = scorm_field_scorm_add_cmi_data($scorm, $flat_tree, $scorm_version);

    $sco_identifiers = [];
    $scos_suspend_data = [];
    foreach ($flat_tree as $sco) {
      if ($sco->scorm_type == 'sco') {
        $sco_identifiers[$sco->identifier] = $sco->id;
        $scos_suspend_data[$sco->id] = scorm_field_default_scorm_cmi_get($account->id(), $scorm->id, 'cmi.suspend_data.' . $sco->id, '');
      }
    }
    $last_user_sco = scorm_field_default_scorm_cmi_get($account->id(), $scorm->id, 'user.sco', '');
    if ($last_user_sco != '') {
      foreach ($flat_tree as $sco) {
        if ($last_user_sco == $sco->id && !empty($sco->launch)) {
          $start_sco = $sco;
        }
      }
    }
    // Add base path for player link.
    global $base_path;
    $start_sco->base_path = $base_path;


    $config = \Drupal::configFactory()->get('scorm_field.settings');
    if (isset($config) && !empty($config->get('decoupled_mode'))) { 
      $decoupled_mode = $config->get('decoupled_mode'); 
    }

    return [
      '#theme' => 'scorm_field_scorm__player',
      '#decoupled_mode' => $decoupled_mode,
      '#scorm_id' => $scorm->id,
      '#node_id' => $nid,
      '#tree' => count($flat_tree) == 2 ? NULL : $tree,
      '#start_sco' => $start_sco,
      '#attached' => [
        'library' => ['scorm_field/scorm-field-scorm-player'],
        'drupalSettings' => [
          'scormFieldScormUIPlayer' => [
            'cmiPaths' => $paths,
            'cmiData' => $data,
            'scoIdentifiers' => $sco_identifiers,
            'cmiSuspendItems' => $scos_suspend_data,
          ],
          'scormVersion' => $scorm_version,
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }
  
  public function getStartSCO($scorm) {
  
     // Get the SCO tree.
    $tree = $this->scormFieldScormPlayerScormTree($scorm);
    $flat_tree = $this->scormFieldScormPlayerFlattenTree($tree);

    // Get the start SCO.
    $start_sco = $this->scormFieldScormPlayerStartSco($flat_tree);
    
    return $start_sco;
  
  }

  /**
   * Traverse the SCORM package data and construct a SCO tree.
   *
   * @param object $scorm
   *   Scorm object.
   * @param int $parent_identifier
   *   Parent identifier.
   *
   * @return array
   *   SCO tree.
   */
  private function scormFieldScormPlayerScormTree($scorm, $parent_identifier = 0) {
    $conenction = $this->database;
    $tree = [];

    $result = $conenction->select('scorm_field_scorm_package_scos', 'sco')
      ->fields('sco', ['id'])
      ->condition('sco.scorm_id', $scorm->id)
      ->condition('sco.parent_identifier', $parent_identifier)
      ->execute();

    while ($sco_id = $result->fetchField()) {
      $sco = $this->scorm_service->scormLoadSco($sco_id);

      $children = $this->scormFieldScormPlayerScormTree($scorm, $sco->identifier);

      $sco->children = $children;

      $tree[] = $sco;
    }

    return $tree;
  }

  /**
   * Helper function to flatten the SCORM tree.
   *
   * @param array $tree
   *   Tree.
   *
   * @return array
   *   SCORM tree.
   */
  private function scormFieldScormPlayerFlattenTree(array $tree) {
    $items = [];

    if (!empty($tree)) {
      foreach ($tree as $sco) {
        $items[] = $sco;
        if (!empty($sco->children)) {
          $items = array_merge($items, $this->scormFieldScormPlayerFlattenTree($sco->children));
        }
      }
    }

    return $items;
  }

  /**
   * Determine the start SCO for the SCORM package.
   *
   * @todo Get last viewed SCO.
   *
   * @param array $flat_tree
   *   Flat tree.
   *
   * @return object
   *   Start SCO.
   */
  private function scormFieldScormPlayerStartSco(array $flat_tree) {
    foreach ($flat_tree as $sco) {
      if (!empty($sco->launch)) {
        return $sco;
      }
    }

    // Failsafe. Just get the first element.
    return array_shift($flat_tree);
  }

}
