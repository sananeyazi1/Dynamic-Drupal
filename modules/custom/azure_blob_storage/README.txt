Azure Blob Storage
================
Azure Blob Storage module contains functions that use Azure Storage SDK to create
archive and upload it to azure blob storage.

 - Bash scripts to be run by cron jobs
 - Drush commands such as: azure:init and azure:upload
 - more

Requirements
===============
 - Azure Storage SDK for PHP
 - Zip extension

Instalation
===============
Install the module as usual, more info can be found on:
https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules

Configuration
=================
Be sure to enable Azure storage which can be uninstalled at production later.

 - Go to Azure storage settings, e.g.:
    admin/config/system/azure-storage

 - Enter your azure storage account name and key.

 - Enter your folder, cron jobs and azure preferences.

 - Add two cron jobs to crontab
    + first for initial start of the process:
        0 1 * * * /bin/bash -c "<REAL_PATH_TO_MODULES_FOLDER>/modules/contrib/azure_storage/azure_init.sh" >> <REAL_PATH_TO_MODULES_FOLDER>/modules/contrib/azure_storage/logs/init_log
    + second for every next part of the file until upload is done
        */5 * * * * /bin/bash -c "<REAL_PATH_TO_MODULES_FOLDER>/modules/contrib/azure_storage/azure_upload.sh" >> <REAL_PATH_TO_MODULES_FOLDER>/modules/contrib/azure_storage/logs/upload_log

Author/Maintainers
======================
 - Stefan Bizhev https://www.drupal.org/u/bizhev
