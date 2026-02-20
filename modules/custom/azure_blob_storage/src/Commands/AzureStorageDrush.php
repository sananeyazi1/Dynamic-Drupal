<?php

namespace Drupal\azure_blob_storage\Commands;

use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class AzureStorageDrush extends DrushCommands {

    /**
     * Starts the backup process.
     *
     * @command azure_storage:init
     * @aliases azure:init
     * @usage azure:init
     *   Starts the uploading process of azure storage backup or exit with error msg.
     */
    public function init() {
      // Upload first part of the backup archive.
      $result = uploadToAzureStorage();
      // If there are blocks uploaded.
      if(is_int($result)){
        $this->output()->writeln((string)t('Backup process started successfully!'));
      }
      // If there was error.
      else{
        $this->output()->writeln((string)t('There was problem starting the backup process: ') . $result);
      }
    }

  /**
   * Uploads part of the backup file.
   *
   * @command azure_storage:upload
   * @aliases azure:upload
   * @usage azure:upload
   *   Uploads next part of the backup archive or exit with error.
   */
  public function upload() {
    // Get module settings.
    $settings = \Drupal::config('azure_blob_storage.settings');

    // If we started the upload process.
    if (file_exists($settings->get('archive_path') . '/' . 'running_azure.lock')) {
      $result = uploadToAzureStorage();
      // If we havent reached the end of the upload -> show processed blocks (mb).
      if ($result != 'Success') {
        $this->output()->writeln((string)t('Processed packages: ') . $result);
      }
      // If we reached the end of file and upload was successful.
      else {
        $this->output()->writeln((string)t('File successfully uploaded!'));
      }
    }
    else{
      $this->output()->writeln((string)t('Backup process has not been started!'));
    }
  }

}
