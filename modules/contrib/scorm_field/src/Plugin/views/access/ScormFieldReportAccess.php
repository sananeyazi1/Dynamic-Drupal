<?php

namespace Drupal\scorm_field\Plugin\views\access;

use Drupal\views\Plugin\views\access\AccessPluginBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *     id = "scorm_field_report_access",
 *     title = @Translation("Scorm field report access"),
 *     help = @Translation("Check if this view can be accessed by type of node and user access rights."),
 * )
 */
class ScormFieldReportAccess extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Scorm field report settings');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $access = \Drupal::service('scorm_field.report_views_access')->access($account);
    if (isset($access) && $access->isAllowed()) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route){
    $route->setRequirement('_scorm_field_report_views_access_check', 'scorm_field.report_views_access::access');
  }

}
