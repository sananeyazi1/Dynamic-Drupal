<?php

namespace Drupal\scorm_field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\node\NodeInterface;

/**
 * Service description.
 */
class ScormFieldCommonService {

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
   * Constructs a ScormFieldService object.
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
   * Get scorm report by Node Id
   *
   * @param integer $nid
   * @return array $report
   */
  public function getScormReportByNodeId(int $nid) {

    $report = $this->entityTypeManager
      ->getStorage('scorm_report')
      ->loadByProperties(['nid' => $nid]);

    return $report;

  }

  /**
   * Check access to view scorm report
   *
   * @param integer $nid
   * @return $access false | true
   */
  public function checkViewReportAccess(int $nid) {

    $access = FALSE;

    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    if ($node instanceof NodeInterface) {
      $field_definitions = $node->getFieldDefinitions();
      foreach ($field_definitions as $machine_name => $field) {
        $className = $field->getClass();
        if ($className === '\Drupal\scorm_field\Plugin\Field\FieldType\ScormFieldScormPackageItemList') {
          if ($node->access('update', $account)) {
            $access = TRUE;
          }
        }        
      }
    }

    return $access;

  }

  /**
   * Save a scorm report.
   */
  public function saveScormReport(int $nid, array $score_data, string $status) {

    $uid = $this->account->id();
    $ip = \Drupal::request()->getClientIp();

    // Define new data
    $new_scorm_data = [
      'nid' => $nid,
      'score_raw' => $score_data['raw'],
      'score_min' => $score_data['min'],
      'score_max' => $score_data['max'],
      'status' => $status
    ];    

    // First we check for any unauthenticated user
    if ($this->account->isAuthenticated()) {
      // Define conditions
      $conditions = [
        'nid' => $nid,
        'uid' => $uid
      ];    
    }
    else {
      $session_uuid = $this->getSessionIDForUnauthenticatedUsers();   
      $conditions = [
        'nid' => $nid,
        'session_uuid' => $session_uuid,
      ];
      $new_scorm_data['session_uuid'] = $session_uuid;       
     }

    // Define the storage
    $storage = $this->entityTypeManager->getStorage('scorm_report');

    $entities = $storage->loadByProperties($conditions);
    if ($entity = reset($entities)) {
      // Sometimes odd things happen with scorm data.
      // If the raw score is the same never allow to change the status.
      if ($entity->getScoreRaw() != $score_data['raw']) {
        $entity->setStatus($status);
      }
      $entity->setScoreRaw($score_data['raw']);
      $entity->setScoreMin($score_data['min']);
      $entity->setScoreMax($score_data['max']);
      $entity->save();
    }
    else {
      // create new record
      // Add new data
      $scorm_report = $storage->create($new_scorm_data);
      $scorm_report->save();

    }
    
  }

  /**
   * Check valid score object
   *
   * @param object $scormScoreObject
   *   The scorm object
   * @return bool
   *   TRUE | FALSE
   */
  public function checkScoreObject($scormScoreObject) {

    $score = FALSE;

    if ($scormScoreObject->raw === '' &&
        $scormScoreObject->min === '' &&
        $scormScoreObject->max === '') {
      $score = FALSE;
    }
    else {
      $score = TRUE;
    }

    return $score;

  }

  /**
   * Creates and returns a unique identifier for unauthenticated users.
   *  
   * @return string $session_key
   *   Unique session key.
   */
  protected function getSessionIDForUnauthenticatedUsers() {
    // Define session.
    $session = \Drupal::request()->getSession();
    // Check if we have already an unique identifier saved into session
    if (!$session->has('core.tempstore.private.owner')) {
     // This generates a unique identifier for the user
     $session->set('core.tempstore.private.owner', Crypt::randomBytesBase64());
    }
    
    $session_key = $session->get('core.tempstore.private.owner');
  
    return $session_key;
  
  }


}


