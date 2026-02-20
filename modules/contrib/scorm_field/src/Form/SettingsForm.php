<?php

namespace Drupal\scorm_field\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure scorm field settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scorm_field_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['scorm_field.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {


    // Display a page description.
    $form['description'] = [
      '#markup' => '<p>' . $this->t('This page allows you to configure scorm field to operate in decoupled mode..') . '</p>',
    ];

    $form['scorm_settings_container'] = [
      '#id' => 'scorm-settings-container',
      '#type' => 'details',
      '#title' => $this->t('Scorm Settings'),
      '#description' => $this->t('Define mode for scorm field'),
      '#open' => TRUE,
    ];

    $form['scorm_settings_container']['decoupled_mode'] = [
      '#title' => $this->t('Decoupled mode'),
      '#type' => 'checkbox',
      '#default_value' => $this->config('scorm_field.settings')->get('decoupled_mode'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('scorm_field.settings')
      ->set('decoupled_mode', $form_state->getValue('decoupled_mode'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
