<?php

namespace Drupal\azure_blob_storage\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class AzureSettingsController.
 */
class AzureSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'azure_blob_storage.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'azure_blob_storage_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('azure_blob_storage.settings');
    $account_name = $config->get('account_name');
    $account_key = $config->get('account_key');
    $read_path = $config->get('read_path');
    $azure_location = $config->get('azure_location');
    $upload_size = $config->get('upload_size');
    $archive_path = $config->get('archive_path');

    $form['account_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Azure storage account name'),
      '#default_value' => $account_name ? $account_name : '',
      '#required' => TRUE,
    ];

    $form['account_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Azure storage account key'),
      '#default_value' => $account_key ? $account_key : '',
      '#size' => 85,
      '#required' => TRUE,
    ];

    $form['read_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to folder for backup'),
      '#default_value' => $read_path ? $read_path : 'sites/default/files/backup',
      '#description' => $this->t('Enter path to the folder that will be backed up starting from docroot'),
      '#required' => TRUE,
    ];

    $form['archive_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to put archive into'),
      '#default_value' => $archive_path ? $archive_path : 'modules/contrib/azure_blob_storage',
      '#description' => $this->t('<i>File will be deleted after upload process has finished.</i>'),
      '#required' => TRUE,
    ];

    $form['azure_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location to upload'),
      '#default_value' => $azure_location ? $azure_location : '',
      '#description' => $this->t('Enter path to (or name of) the folder where the files will be backed up on Azure'),
      '#required' => TRUE,
    ];

    $form['upload_size'] = [
      '#type' => 'number',
      '#title' => $this->t('How many MBs to upload per cron job'),
      '#default_value' => $upload_size ? $upload_size : 50,
      '#description' => $this->t('Make sure that you don\'t hit any server limitations. (<i>Max 300 MB.</i>)'),
      '#required' => TRUE,
    ];

    $form['actions']['test_connection'] = array
    (
      '#type' => 'submit',
      '#value' => t('Test connection'),
      '#submit' => ['testConnection'],
      '#button_type' => 'primary',
      '#weight' => 100,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('azure_blob_storage.settings')
      ->set('account_name', $form_state->getValue('account_name'))
      ->set('account_key', $form_state->getValue('account_key'))
      ->set('read_path', $form_state->getValue('read_path'))
      ->set('azure_location', $form_state->getValue('azure_location'))
      ->set('upload_size', $form_state->getValue('upload_size'))
      ->set('archive_path', $form_state->getValue('archive_path'))
      ->save();
      parent::submitForm($form, $form_state);
  }

}
