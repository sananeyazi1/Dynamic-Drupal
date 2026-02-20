<?php

namespace Drupal\Tests\plupload\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests related to the PlUpload element.
 *
 * @group plupload
 */
class PluploadFileElementTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'plupload',
    'plupload_test',
  ];

  /**
   * Tests that the plupload element appears.
   */
  public function testPluploadElement() {
    $this->container->get('router.builder')->rebuild();
    $form = \Drupal::formBuilder()
      ->getForm('\Drupal\plupload_test\PluploadTestForm');
    $this->render($form);

    $xpath_base = "//div[contains(@class, 'form-item-plupload')]";

    // Label.
    $this->assertEmpty($this->xpath("$xpath_base/label[text()='Not Plupload element']"));
    $this->assertNotEmpty($this->xpath("$xpath_base/label[text()='Plupload']"));

    // Element where plupload is attached to.
    $this->assertNotEmpty($this->xpath("$xpath_base/div[contains(@class, 'plupload-element')]"));

    // Js is attached.
    $module_dir = \Drupal::service('extension.list.module')->getPath('plupload');
    $this->assertNotEmpty($this->xpath("//html/body/script[contains(@src, 'libraries/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js')]"));
    $this->assertNotEmpty($this->xpath("//html/body/script[contains(@src, '$module_dir/plupload.js')]"));

    // Settings.
    $result = $this->xpath("//html/body/script[contains(@data-drupal-selector, 'drupal-settings-json')]");
    $this->assertNotEmpty($result);
    $drupal_settings = json_decode((string) $result[0]);

    $this->assertObjectHasProperty('plupload', $drupal_settings);
    $this->assertObjectHasProperty('filters', $drupal_settings->plupload->{"edit-plupload"});

    $filters = $drupal_settings->plupload->{"edit-plupload"}->filters;
    $this->assertEquals('Allowed files', $filters[0]->title);
    $this->assertEquals('zip', $filters[0]->extensions);
  }

}
