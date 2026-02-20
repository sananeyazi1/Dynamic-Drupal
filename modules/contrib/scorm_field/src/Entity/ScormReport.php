<?php

namespace Drupal\scorm_field\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\scorm_field\ScormReportInterface;
use Symfony\Component\HttpFoundation;

/**
 * Class ScormReport.
 *
 * @package Drupal\scorm_field\Entity
 *
 * @ContentEntityType(
 *   id = "scorm_report",
 *   label = @Translation("Scorm Report"),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData"
 *   },
 *   base_table = "scorm_report",
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class ScormReport extends ContentEntityBase implements ScormReportInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    $values += [
      'uid' => \Drupal::currentUser()->id(),
      'ip' => \Drupal::request()->getClientIp(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->setOwnerId($account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Owner'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['nid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Node ID'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['score_raw'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Score raw'))
      ->setDefaultValue(0);

    $fields['score_max'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Score max'))
      ->setDefaultValue(0);      

    $fields['score_min'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Score min'))
      ->setDefaultValue(0);      

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Scorm status'));

    $fields['ip'] = BaseFieldDefinition::create('string')
      ->setLabel(t('IP'));
      
    $fields['session_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Session UUID'));      

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getNode() {
    return $this->get('nid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeId() {
    return $this->get('nid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return (int) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->get('status')->setValue($status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getScoreRaw() {
    return (int) $this->get('score_raw')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setScoreRaw($score_raw) {
    $this->get('score_raw')->setValue($score_raw);
    return $this;
  }  

  /**
   * {@inheritdoc}
   */
  public function getScoreMax() {
    return (int) $this->get('score_max')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setScoreMax($score_max) {
    $this->get('score_max')->setValue($score_max);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getScoreMin() {
    return (int) $this->get('score_min')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setScoreMin($score_min) {
    $this->get('score_min')->setValue($score_min);
    return $this;
  } 

}
