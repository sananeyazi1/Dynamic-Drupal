<?php

namespace Drupal\scorm_field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Scorm report entity.
 *
 * @ingroup scorm_field
 * @package Drupal\scorm_field
 */
interface ScormReportInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {


  /**
   * Gets node object.
   *
   * @return \Drupal\node\NodeInterface
   *   The node entity.
   */
  public function getNode();

  /**
   * Gets node id.
   *
   * @return int
   *   The Node id.
   */
  public function getNodeId();


  /**
   * Gets scorm status.
   *
   * @return string
   *   The Scorm status.
   */
  public function getStatus();

  /**
   * Sets scorm status.
   *
   * @param string $status
   *   Status code.
   *
   * @return \Drupal\scorm_field\ScormCompletionInterface
   *   The status for the scorm completion.
   */
  public function setStatus($status);

  /**
   * Gets scorm score_raw.
   *
   * @return int
   *   The Scorm score_raw.
   */
  public function getScoreRaw();

  /**
   * Sets scorm score_raw.
   *
   * @param int $score_raw
   *   Score raw.
   *
   * @return \Drupal\scorm_field\ScormCompletionInterface
   *   The ScormCompletion entity.
   */
  public function setScoreRaw($score_raw); 
  
  /**
   * Gets scorm completion score_max.
   *
   * @return int
   *   The ScormCompletion score_max.
   */
  public function getScoreMax();

  /**
   * Sets scorm completion score_max.
   *
   * @param int $score_max
   *   Score max.
   *
   * @return \Drupal\scorm_field\ScormCompletionInterface
   *   The CourseCompletion entity.
   */
  public function setScoreMax($score_max);

  /**
   * Gets scorm completion score_min.
   *
   * @return int
   *   The ScormCompletion score_min.
   */
  public function getScoreMin();

  /**
   * Sets scorm completion score_min.
   *
   * @param int $score_min
   *   Score min.
   *
   * @return \Drupal\scorm_field\ScormCompletionInterface
   *   The CourseCompletion entity.
   */
  public function setScoreMin($score_min);    
  

}
