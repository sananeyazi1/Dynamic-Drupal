<?php

namespace Drupal\Tests\az_blob_fs\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Tests Azure Blog Storage configuration form.
 *
 * @group az_blob_fs
 */
class AzBlobFsConfigFormTest extends AzBlobFsTestBase {

  use StringTranslationTrait;

  /**
   * User with proper permissions for module configuration.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * User with content access.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $normalUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'az_blob_fs',
    'image',
    'key',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test the Azure Blog Storage config form access.
   */
  public function testAzBlobFsConfigurationFormAccess() {

    // Setup admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer azure blob storage',
      'administer keys',
    ]);

    // Perform login.
    $this->drupalLogin($this->adminUser);

    // Access to the path.
    $this->drupalGet(Url::fromRoute('az_blob_fs.settings_form'));

    // Check the response returned by Drupal.
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test the Azure Blog Storage config form no access.
   */
  public function testAzBlobFsConfigurationFormNoAccess() {

    $this->normalUser = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($this->normalUser);

    // Access to the path.
    $this->drupalGet(Url::fromRoute('az_blob_fs.settings_form'));

    // Check the response returned by Drupal.
    $this->assertSession()->statusCodeEquals(403);

    // Logout as normal user and repeat the former cycle.
    $this->drupalLogout();
    $this->drupalGet(Url::fromRoute('az_blob_fs.settings_form'));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test the Azure Blog Storage config form.
   */
  public function testAzBlobFsConfigurationForm() {

    // Setup admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer azure blob storage',
      'administer keys',
    ]);

    // Perform login.
    $this->drupalLogin($this->adminUser);

    // Go to the Key list page.
    $this->drupalGet('admin/config/system/keys');
    $this->assertSession()->statusCodeEquals(200);

    // Verify that the "no keys" message displays.
    $this->assertSession()->responseContains(
      new FormattableMarkup('No keys are available. <a href=":link">Add a key</a>.', [
        ':link' => Url::fromRoute('entity.key.add_form')->toString(),
      ]));

    // Add a key.
    $this->drupalGet('admin/config/system/keys/add');
    $edit = [
      'id' => $this->azBlobFsConfig['az_blob_account_key_name'],
      'label' => 'Azure Access Key',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_input_settings[key_value]' => $this->azBlobFsConfig['az_blob_account_key'],
    ];
    $this->submitForm($edit, 'Save');

    // Go to the Key list page.
    $this->drupalGet('admin/config/system/keys');
    $this->assertSession()->statusCodeEquals(200);

    // Verify that the "no keys" message does not display.
    $this->assertSession()->pageTextNotContains('No keys are available.');

    // Get Azure Blob Storage config page.
    $this->drupalGet(Url::fromRoute('az_blob_fs.settings_form'));
    $this->assertSession()->statusCodeEquals(200);

    // Update Azure Blob Storage config.
    $edit = [
      'az_blob_account_name' => $this->azBlobFsConfig['az_blob_account_name'],
      'az_blob_container_name' => $this->azBlobFsConfig['az_blob_container_name'],
      'az_blob_account_key_name' => $this->azBlobFsConfig['az_blob_account_key_name'],
      'az_blob_protocol' => $this->azBlobFsConfig['az_blob_protocol'],
      'az_blob_cdn_host_name' => $this->azBlobFsConfig['az_blob_cdn_host_name'],
    ];
    $this->drupalGet(Url::fromRoute('az_blob_fs.settings_form'));
    $this->submitForm($edit, $this->t('Save configuration'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
