## Azure Storage Blob File System

This module creates a stream wrapper scheme in order to store files
on the Azure Storage cloud service.

## Required

You will need a Microsoft Azure Blob storage account or service running
in order to use this.

* Account name is required.
* Account key is required.

See: https://azure.microsoft.com/services/storage/blobs

Azure Portal: https://portal.azure.com

## Notes

This is assuming you already have an account setup.

Create or use an existing Azure container. Record container name. This
will be used later for configuration in Drupal.

In Azure, Under Security + networking, when managing the container. You
should find Access keys. You will need storage account name and also one
of the keys. Connection string is not required. This will be configured
and stored in Drupal.

Also make sure access level is set to Container, if you are storing and
sharing public assets.

### Composer Dependencies

```bash
composer require microsoft/azure-storage-blob
```

## Setup/Usage

Installing through composer is recommended:

```bash
composer require drupal/az_blob_fs
drush en az_blob_fs
```

1. Install and enabled module like any other contrib module.
2. Setup and configure service here:
**/admin/config/media/azure-blob-file-system**
3. Set your default file system to Azure here:
**/admin/config/media/file-system**

## Field Setup

If using Media or file fields, adjust storage settings on the field. Set
the "Upload destination" to "Azure Blob Storage".

If using Media & Media library core modules. See below:

For example, the media image field, can be used to store images in Azure,
as blobs.

Navigate to "Structure" -> "Media Types" -> "Image" -> "Manage Fields"

- Edit Media field image -> "Field Settings"

You should see two options:

* Public files
* Azure Blob Storage

Select Azure Blob Storage. This option should be made available on file
fields across the board.

## Roadmap

* Support for private containers.

## Credits

* https://www.drupal.org/project/s3fs
* https://www.drupal.org/project/image_style_warmer

## Maintainers

George Anderson (geoanders)
https://www.drupal.org/u/geoanders

Yahya Al Hamad (yahyaalhamad)
https://www.drupal.org/u/yahyaalhamad
