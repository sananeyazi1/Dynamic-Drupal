<?php

namespace Drupal\scorm_field\Plugin\Field\FieldType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * Plugin implementation of the 'scorm_field_scorm_package' field type.
 *
 * @FieldType(
 *   id = "scorm_field_scorm_package",
 *   label = @Translation("Scorm field"),
 *   description = @Translation("This field stores the ID of a Scorm package file."),
 *   category = @Translation("Reference"),
 *   default_widget = "file_generic",
 *   default_formatter = "scorm_field_scorm_formatter",
 *   list_class = "\Drupal\scorm_field\Plugin\Field\FieldType\ScormFieldScormPackageItemList",
 *   constraints = {"ReferenceAccess" = {}, "FileValidation" = {}}
 * )
 */
class ScormFieldScormPackage extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = parent::defaultFieldSettings();
    $settings['file_extensions'] = 'zip';
    $settings['file_directory'] = 'scorm_field';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    return $element;
  }

}
