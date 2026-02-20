<?php

namespace Drupal\azure_storage\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'azure_storage.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'azure_storage_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('azure_storage.settings');

    $form['protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Endpoints Protocol'),
      '#description' => $this->t('Default endpoints protocol to use.'),
      '#default_value' => $config->get('protocol'),
      '#options' => [
        'http' => $this->t('Http'),
        'https' => $this->t('Https'),
      ],
      '#required' => TRUE,
    ];

    $form['account_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account Name'),
      '#description' => $this->t('The Account Name for Azure Storage'),
      '#default_value' => $config->get('account_name'),
      '#required' => TRUE,
    ];

    $form['test_account_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Account Key (test)'),
      '#description' => $this->t('The Account Key for Azure Storage'),
      '#default_value' => $config->get('test_account_key'),
      '#required' => TRUE,
    ];

    $form['live_account_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Account Key (live)'),
      '#description' => $this->t('The Account Key for Azure Storage'),
      '#default_value' => $config->get('live_account_key'),
      '#required' => TRUE,
    ];

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mode'),
      '#options' => [
        'test' => $this->t('Test'),
        'live' => $this->t('Live'),
      ],
      '#default_value' => $config->get('mode'),
    ];

    $form['endpoint_suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint Suffix'),
      '#description' => $this->t('The Endpoint Suffix for Azure Storage'),
      '#default_value' => $config->get('endpoint_suffix'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('azure_storage.settings')
      ->set('protocol', $form_state->getValue('protocol'))
      ->set('account_name', $form_state->getValue('account_name'))
      ->set('test_account_key', $form_state->getValue('test_account_key'))
      ->set('live_account_key', $form_state->getValue('live_account_key'))
      ->set('mode', $form_state->getValue('mode'))
      ->set('endpoint_suffix', $form_state->getValue('endpoint_suffix'))
      ->save();
  }

}
