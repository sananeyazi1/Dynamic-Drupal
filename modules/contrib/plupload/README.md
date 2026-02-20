# Plupload integration

Provides integration for the [Plupload widget](https://plupload.com) into Drupal. Plupload is a GPL licensed multiple file uploading tool that can present widgets in Flash, Gears, HTML 5, Silverlight, BrowserPlus, and HTML4 depending on the capabilities of the client computer.

If you enabled Plupload on your site by installing contributed modules and you're experiencing any problems you need to post issues in the [module that provides Plupload integration](https://www.drupal.org/project/plupload#modules) to your site. Their developers will redirect issues to Plupload integration queue if they think the problem comes from this module.


## Table of contents

- Requirements
- Installation
- Configuration
- For developers
- Maintainers


## Requirements

This module requires the [Plupload library](https://plupload.com).


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

To install the Plupload library:

1. Download version 2.3.9 of it from
   https://github.com/moxiecode/plupload/releases
1. Unzip it into libraries folder, so that there's a
   `libraries/plupload/js/plupload.full.min.js` file, in addition to the other
   files included in the library
1. Remove "examples" folder from libraries folder as it could constitute a
   security risk to your site. See https://drupal.org/node/1895328 and
   https://drupal.org/node/1189632 for more info.

Additional instructions for installing the Plupload library via `composer` can
be found at
[this documentation page](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/plupload-and-related-modules/using-composer-to-install-the-plupload-library).


## Configuration

At this time, this module only provides a 'plupload' form element type that
other modules can use for providing multiple file upload capability to their
forms. It does not provide any end-user functionality on its own.


## For developers

Plupload from element can be used like this:

```
$form['my_element'] = array(
  '#type' => 'plupload',
  '#title' => t('Upload files'),
  '#description' => t('This multi-upload widget uses Plupload library.'),
  '#autoupload' => TRUE,
  '#autosubmit' => TRUE,
  '#submit_element' => '#id-of-your-submit-element',
  '#upload_validators' => array(
    'file_validate_extensions' => array('jpg jpeg gif png txt doc xls pdf ppt
     pps odt ods odp'),
    'my_custom_file_validator' => array('some validation criteria'),
  );
  '#plupload_settings' => array(
    'runtimes' => 'html5',
    'chunk_size' => '1mb',
  ),
  '#event_callbacks' => array(
    'FilesAdded' => 'Drupal.mymodule.filesAddedCallback',
    'UploadComplete' => 'Drupal.mymodule.uploadCompleteCallback',
  ),
);
```

There are few optional properties of this array that have special meaning:

- `#autoupload`: Set this to `TRUE` if you want Plupload to start uploading
  immediately after files are added.
  Defaults to `FALSE`.

- `#autosubmit`: Set this to `TRUE` if you want Plupload to autosubmit
  your form after automatic upload has finished.
  Defaults to `FALSE`.
  Has to be used in combination with `#autoupload`.

- `#submit_element`: Specify which submit element Plupload shall use to submit
  the form. Can also be used in combination with `#autoupload`
  and `#autosubmit`.
  See: https://drupal.org/node/1935256.

- `#upload_validators`: An array of validation function/validation criteria
  pairs, that will be passed to `file_validate()`.
  Defaults to:
```
  '#upload_validators' => array(
    'file_validate_extensions' => array('jpg jpeg gif png txt doc xls pdf ppt
     pps odt ods odp'),
  );
```

- `#plupload_settings`: Array of settings, that will be passed to Plupload
 library. See [Plupload documentation](https://www.plupload.com/documentation.php)
  Defaults to:
```
  '#plupload_settings' => array(
    'runtimes' => 'html5,flash,html4',
    'url' => url('plupload-handle-uploads', array('query' =>
      array('plupload_token' => drupal_get_token('plupload-handle-uploads')))),
    'max_file_size' => file_upload_max_size() . 'b',
    'chunk_size' => '1mb',
    'unique_names' => TRUE,
    'flash_swf_url' => file_create_url($library_path .
     '/js/plupload.flash.swf'),
    'silverlight_xap_url' => file_create_url($library_path .
     '/js/plupload.silverlight.xap'),
  ),
```

- `#event_callbacks`: Array of callbacks that will be passed to js.
  See full documentation about events in
  [Plupload library](https://www.plupload.com/example_events.php).


## Maintainers

- Paulo Henrique Cota Starling - [paulocs](https://www.drupal.org/u/paulocs)
- Marc Orcau - [budalokko](https://www.drupal.org/u/budalokko)
- David Rothstein - [David_Rothstein](https://www.drupal.org/u/David_Rothstein)
- Alex Bronstein - [effulgentsia](https://www.drupal.org/u/effulgentsia)
- Greg Knaddison - [greggles](https://www.drupal.org/u/greggles)
- Jacob Singh - [JacobSingh](https://www.drupal.org/u/JacobSingh)
- Janez Urevc - [slashrsm](https://www.drupal.org/u/slashrsm)
