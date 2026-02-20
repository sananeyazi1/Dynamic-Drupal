<?php

namespace Drupal\scorm_field\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\node\NodeInterface;

/**
 * Class ScormFieldController.
 */
class ScormFieldScormController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function scormIntegrateSco($scorm_sco) {
    $scorm_service = \Drupal::service('scorm_field.scorm');
    $sco = $scorm_service->scormLoadSco($scorm_sco);
    // Does the SCO have a launch property ?
    if (!empty($sco->launch)) {
      $query = [];

      // Load the SCO data.
      $scorm = $scorm_service->scormLoadById($sco->scorm_id);

      // Remove the URL parameters from the launch URL.
      if (!empty($sco->attributes['parameters'])) {
        $sco->launch .= $sco->attributes['parameters'];
      }
      $parts = explode('?', $sco->launch);
      $launch = array_shift($parts);

      if (!empty($parts)) {
        // Failsafe - in case a launch URL has 2 or more '?'.
        $parameters = implode('&', $parts);
      }

      // Get the SCO location on the filesystem.
      $sco_location = "{$scorm->extracted_dir}/$launch";
      $sco_path = \Drupal::service('file_url_generator')->generateAbsoluteString($sco_location);

      // Where there any parameters ? If so, prepare them for Drupal.
      if (!empty($parameters)) {
        foreach (explode('&', $parameters) as $param) {
          list($key, $value) = explode('=', $param);
          $query[$key] = !empty($value) ? $value : '';
        }

        if ($query) {
          $query = UrlHelper::buildQuery($query);
          $sco_path = $sco_path . '?' . $query;
        }
      }

      return new TrustedRedirectResponse($sco_path);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Scorm data commit method.
   * 
   * @param int $scorm_id
   *  The scorm id.
   * @param int $scorm_sco_id
   *  The scorm sco id.
   * @param int $nid
   *  The node id.
   */
  public function scormCommit($scorm_id, $scorm_sco_id, $nid) {
    $data_content = $GLOBALS['request']->getContent();
    $status = FALSE;
    $currentUser = \Drupal::currentUser();
    $scorm_common_service = \Drupal::service('scorm_field.common_service');

    // Read the entity id from paramter
    if (is_numeric($nid)) {
      $entity = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($nid);
    }

    if (!empty($_POST['data'])) {
      $data = json_decode($_POST['data']);
    }
    elseif ($data_content) {
      $data = json_decode($data_content);
    }

    if (!empty($data)) {
      if (!empty($data->cmi->interactions)) {
        $_SESSION['scorm_field_scorm_answer_results'] = [
          'scorm_field_scorm_id' => $scorm_id,
          'scorm_field_scorm_sco_id' => $scorm_sco_id,
          'data' => $data,
        ];
      }
      $scorm_service = \Drupal::service('scorm_field.scorm');
      $scorm = $scorm_service->scormLoadById($scorm_id);
      \Drupal::moduleHandler()->invokeAll('scorm_field_scorm_commit', [
        $scorm,
        $scorm_sco_id,
        $nid,
        $data,
      ]);


      if(isset($data->cmi->core->lesson_status)) {
        $status = $data->cmi->core->lesson_status;
      }
      if(isset($data->cmi->core->completion_status)) {
        $status = $data->cmi->core->completion_status;
      }      

      $score_data = [
        'raw' => $data->cmi->core->score->raw,
        'min' => $data->cmi->core->score->min,
        'max' => $data->cmi->core->score->max,
      ];


      
      // Save the scorm report if we have score data and status
      if ( isset($status) && $status != FALSE && isset($score_data) && !empty($score_data) ) {      
        $scorm_common_service->saveScormReport($nid, $score_data, $status);
      } 

      return new JsonResponse(['success' => 1]);

    }
    else {
      return new JsonResponse(['error' => 1, 'message' => 'no data received']);
    }
  }

}


