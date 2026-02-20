<?php

namespace Drupal\Tests\plupload\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base class for Plupload Web driver functional test base.
 *
 * @group plupload
 */
abstract class PluploadWebDriverTestBase extends WebDriverTestBase {

  /**
   * Add a predefined file to Plupload upload queue.
   *
   * @param string $path
   *   The path to the file.
   */
  protected function addFile(string $path): void {

    $session = $this->getSession();

    // Add a fake input element.
    $input = <<<JS
      jQuery('#edit-plupload').append('<input type=\'file\' name=\'fakefile\'>');
JS;
    $session->evaluateScript($input);

    // Populate the field with uploaded file.
    $session->getPage()->attachFileToField('fakefile', $path);

    // Add to plupload queue.
    $drop = <<<JS
      jQuery('#edit-plupload').pluploadQueue().addFile(jQuery('input[name="fakefile"]')[0]);
JS;
    $session->evaluateScript($drop);
  }

  /**
   * Returns the path to a test file.
   *
   * @param string $name
   *   The name of the file.
   *
   * @return string
   *   Path of the test file.
   */
  protected function getTestFilePath(string $name): string {
    return \Drupal::service('extension.list.module')->getPath('plupload') . '/tests/fixtures/files/' . $name;
  }

}
