<?php

namespace Drupal\az_blob_fs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Azure Blob Filesystem Settings Form.
 */
class AzBlobFsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'az_blob_fs.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('az_blob_fs.settings');

    $form['#prefix'] = '<div id="azblob-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['az_blob_account_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account Name'),
      '#maxlength' => 255,
      '#size' => 60,
      '#required' => TRUE,
      '#default_value' => $config->get('az_blob_account_name'),
    ];

    $key_collection_url = Url::fromRoute('entity.key.collection')->toString();
    $form['keys'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Keys'),
      '#description' => $this->t('Use keys managed by the key module. <a href=":keys">Manage keys</a>', [
        ':keys' => $key_collection_url,
      ]),
      '#tree' => FALSE,
    ];

    $form['keys']['az_blob_account_key_name'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Account Key'),
      '#description' => $this->t('The Azure Blog Storage Account Key.'),
      '#empty_option' => $this->t('- Select Key -'),
      '#default_value' => $config->get('az_blob_account_key_name'),
      '#key_filters' => ['type' => 'authentication'],
      '#required' => TRUE,
    ];

    $form['az_blob_container_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Container name'),
      '#description' => $this->t('Create a blob container on from your storage account with public permissions for the container.'),
      '#maxlength' => 255,
      '#size' => 60,
      '#default_value' => $config->get('az_blob_container_name'),
    ];

    $protocol = 'https';
    if (!empty($config->get('az_blob_protocol'))) {
      $protocol = $config->get('az_blob_protocol');
    }
    $form['az_blob_protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Protocol'),
      '#description' => $this->t('The protocol will be used for the blob endpoint. Defaults to https://.'),
      '#empty_option' => $this->t('- Select Protocol -'),
      '#options' => [
        'http' => $this->t('http://'),
        'https' => $this->t('https://'),
      ],
      '#default_value' => $protocol,
      '#required' => TRUE,
    ];

    $form['az_blob_cdn_host_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CDN Hostname'),
      '#description' => $this->t('If you have Azure CDN configured, you can specify hostname and blobs will be served from here instead of origin.'),
      '#field_prefix' => $protocol . '://',
      '#size' => 15,
      '#default_value' => $config->get('az_blob_cdn_host_name'),
    ];

    $form['az_blob_local_emulator'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use a local storage emulator.'),
      '#default_value' => $config->get('az_blob_local_emulator'),
      '#ajax' => [
        'callback' => [$this, 'emulatorValues'],
        'event' => 'click',
        'wrapper' => 'azblob-form-wrapper',
      ],
    ];

    $form['az_blob_local_ip'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local IP'),
      '#description' => $this->t('The local IP for your Azure storage emulator.'),
      '#maxlength' => 15,
      '#size' => 15,
      '#default_value' => $config->get('az_blob_local_ip'),
      '#states' => [
        'visible' => [
          ':input[name="az_blob_local_emulator"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['az_blob_local_port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Local port'),
      '#description' => $this->t('The local port for your Azure storage emulator.'),
      '#maxlength' => 10,
      '#size' => 10,
      '#default_value' => $config->get('az_blob_local_port'),
      '#states' => [
        'visible' => [
          ':input[name="az_blob_local_emulator"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $imageStyleOptions = image_style_options(FALSE);
    $initialImageStyles = $config->get('az_blob_initial_image_styles');
    $form['image_styles'] = [
      '#type' => 'details',
      '#title' => $this->t('Image styles'),
      '#description' => $this->t('Configure image styles which will be created initially or via queue worker for image warming.'),
      '#open' => FALSE,
      '#attributes' => [
        'class' => [
          'image-style-warmer',
          'image-styles-wrapper',
          'clearfix',
        ],
      ],
    ];

    $form['image_styles']['az_blob_initial_image_styles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Initial image styles'),
      '#description' => $this->t('Select image styles which will be created initially for an image.'),
      '#options' => $imageStyleOptions,
      '#default_value' => !empty($initialImageStyles) ? $initialImageStyles : [],
      '#attributes' => [
        'class' => [
          'az-blob-fs',
          'image-styles',
          'initial-image-styles',
        ],
      ],
    ];

    $queueImageStyles = $config->get('az_blob_queue_image_styles');
    $form['image_styles']['az_blob_queue_image_styles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Queue image styles'),
      '#description' => $this->t('Select image styles which will be created via queue worker.'),
      '#options' => $imageStyleOptions,
      '#default_value' => !empty($queueImageStyles) ? $queueImageStyles : [],
      '#attributes' => [
        'class' => [
          'az-blob-fs',
          'image-styles',
          'queue-image-styles',
        ],
      ],
    ];

    $form['#attached']['library'][] = 'az_blob_fs/az_blob_fs.admin';

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback for form checkbox. Complete default form values on click.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form values.
   *
   * @return array
   *   The form with default values filled.
   */
  public function emulatorValues(array &$form, FormStateInterface $form_state): array {
    $form['az_blob_local_ip']['#value'] = '127.0.0.1';
    $form['az_blob_local_port']['#value'] = '10000';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $form_state->setValue('az_blob_initial_image_styles', array_filter($form_state->getValue('az_blob_initial_image_styles')));
    $form_state->setValue('az_blob_queue_image_styles', array_filter($form_state->getValue('az_blob_queue_image_styles')));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('az_blob_fs.settings');
    $config
      ->set('az_blob_account_name', $form_state->getValue('az_blob_account_name'))
      ->set('az_blob_protocol', $form_state->getValue('az_blob_protocol'))
      ->set('az_blob_container_name', $form_state->getValue('az_blob_container_name'))
      ->set('az_blob_cdn_host_name', $form_state->getValue('az_blob_cdn_host_name'))
      ->set('az_blob_local_ip', $form_state->getValue('az_blob_local_ip'))
      ->set('az_blob_local_port', $form_state->getValue('az_blob_local_port'))
      ->set('az_blob_local_emulator', $form_state->getValue('az_blob_local_emulator'))
      ->set('az_blob_account_key_name', $form_state->getValue('az_blob_account_key_name'))
      ->set('az_blob_initial_image_styles', $form_state->getValue('az_blob_initial_image_styles'))
      ->set('az_blob_queue_image_styles', $form_state->getValue('az_blob_queue_image_styles'))
      ->save();
  }

}
