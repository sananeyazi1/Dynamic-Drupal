<?php

/**
 * Implements hook_drush_command().
 */
function azure_blob_storage_drush_command() {

  $commands['azure-init'] = [
    'description' => 'Starts the uploading process of azure storage backup or exit with error msg.',
    'aliases' => ['azure:init'],
    'examples' => [
      'drush azure:init' => 'Starts the backup process to azure storage.',
    ],
  ];

  $commands['azure-upload'] = [
    'description' => 'Uploads next part of the backup archive or exit with error.',
    'aliases' => ['azure:upload'],
    'examples' => [
      'drush azure:upload' => 'Uploads next part of the backup or exit with error msg.',
    ],
  ];

  return $commands;
}

/**
 * Starts the uploading process of azure storage backup or exit with error msg.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_azure_blob_storage_azure_init() {
  // Upload first part of the backup archive.
  $result = uploadToAzureStorage();
  // If there are blocks uploaded.
  if(is_int($result)){
    drush_print('Backup process started successfully!');
  }
  // If there was error.
  else{
    drush_print('There was problem starting the backup process: ' . $result);
  }
}

/**
 * Uploads next part of the backup archive or exit with error.
 * drush_[MODULE_NAME]_[COMMAND_NAME]().
 */
function drush_azure_blob_storage_azure_upload() {
  // If we started the upload process.
  if (file_exists('running_azure.lock')) {
    $result = uploadToAzureStorage();
    // If we havent reached the end of the upload -> show processed blocks (mb).
    if ($result != 'Success') {
      drush_print('Processed packages: ' . $result);
    }
    // If we reached the end of file and upload was successful.
    else {
      drush_print('File successfully uploaded!');
    }
  }
  else{
    drush_print('Backup process has not been started!');
  }
}
