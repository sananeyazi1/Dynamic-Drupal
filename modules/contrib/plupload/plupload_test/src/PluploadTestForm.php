<?php

namespace Drupal\plupload_test;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plupload test form class.
 */
class PluploadTestForm extends FormBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Constructs a PluploadTestForm object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(FileSystemInterface $file_system, StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return '_plupload_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['plupload'] = [
      '#type' => 'plupload',
      '#title' => 'Plupload',
      '#upload_validators' => [
        'file_validate_extensions' => ['zip'],
      ],
    ];

    $form['result_message'] = [
      '#markup' => '<div class="result_message"></div>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['ajax_submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Ajax submit'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('plupload') as $uploaded_file) {
      if ($uploaded_file['status'] != 'done') {
        $form_state->setErrorByName('plupload', $this->t("Upload of %filename failed.", ['%filename' => $uploaded_file['name']]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Create target directory if necessary.
    $destination = $this->config('system.file')
      ->get('default_scheme') . '://plupload-test';
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $saved_files = [];

    foreach ($form_state->getValue('plupload') as $uploaded_file) {
      $file_uri = $this->streamWrapperManager->normalizeUri($destination . '/' . $uploaded_file['name']);

      // Move file without creating a new 'file' entity.
      $this->fileSystem->move($uploaded_file['tmppath'], $file_uri);

      // @todo When https://www.drupal.org/node/2245927 is resolved,
      // use a helper to save file to file_managed table
      $saved_files[] = $file_uri;
    }
    if (!empty($saved_files)) {
      $this->messenger()->addStatus('Files uploaded correctly: ' . implode(', ', $saved_files) . '.');
    }
  }

  /**
   * Custom ajax form submission.
   */
  public function ajaxSubmit(array $form, FormStateInterface $form_state) {
    $num_files = count($form_state->getValue('plupload'));

    $response = new AjaxResponse();
    $response->addCommand(
      new HtmlCommand(
        '.result_message',
        '<div class="my_top_message">' . $this->t('Uploaded @num_files files', [
          '@num_files' => $num_files,
        ]) . '</div>'),
    );
    $response->addCommand(new ReplaceCommand(NULL, $form));
    return $response;
  }

}
