<?php

namespace Drupal\Tests\minisite\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;

/**
 * Tests the minisite field validation.
 *
 * @group minisite
 */
class FieldValidationTest extends MinisiteTestBase {

  use FieldUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
  }

  /**
   * Tests the required property on minisite field.
   */
  public function testValidateRequired() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')
      ->getStorage('node');
    $field_name = strtolower($this->randomMachineName());
    $this->createMinisiteField($field_name, 'node', $this->contentType, [], ['required' => '1']);
    $field = FieldConfig::loadByName('node', $this->contentType, $field_name);

    // Try to post a new node without uploading a file.
    $edit = [];
    $edit['title[0][value]'] = $this->randomMachineName();
    $this->drupalGet('node/add/' . $this->contentType);
    $this->submitForm($edit, 'Save');
    $this->assertSession()
      ->responseContains($this->t('@title field is required.', ['@title' => $field->getLabel()]));

    // Create a new node with the uploaded file.
    $test_file = $this->getTestArchiveValid();
    $nid = $this->uploadNodeFile($test_file, $field_name, $this->contentType);
    $this->assertTrue($nid !== FALSE, new FormattableMarkup('uploadNodeFile(@test_file, @field_name, @type_name) succeeded', [
      '@test_file' => $test_file->getFileUri(),
      '@field_name' => $field_name,
      '@type_name' => $this->contentType,
    ]));

    $node_storage->resetCache([$nid]);
    $node = $node_storage->load($nid);

    $node_file = File::load($node->{$field_name}->target_id);
    $this->assertFileUriExists($node_file, 'File exists after uploading to the required field.');
    $this->assertFileEntryExists($node_file, 'File entry exists after uploading to the required field.');
  }

  /**
   * Tests the archive format on minisite field.
   */
  public function testValidateArchiveFormat() {
    $field_name = strtolower($this->randomMachineName());
    $this->createMinisiteField($field_name, 'node', $this->contentType, [], ['required' => '1']);

    // Try uploading a file with correct extension, but invalid format.
    $test_file = $this->getTestArchiveInvalidFormat();
    $this->uploadNodeFile($test_file, $field_name, $this->contentType);
    $this->assertSession()->responseContains($this->t('The specified file %filename could not be uploaded.', ['%filename' => $test_file->getFilename()]));
    $this->assertSession()->responseContains($this->t('File @filename is not an archive file.', ['@filename' => $test_file->getFilename()]));
  }

  /**
   * Test setting extensions on field configuration page.
   */
  public function testFieldAllowedExtensions() {
    $field_name = strtolower($this->randomMachineName());
    $this->createMinisiteField($field_name, 'node', $this->contentType);
    $path = 'admin/structure/types/manage/' . $this->contentType . '/fields/node.' . $this->contentType . '.' . $field_name;

    // Valid extensions.
    $allowed_extensions = 'html, htm, js, css, png, txt';

    // Single denied extensions entered.
    $denied_extensions = 'scr';
    $edit['settings[minisite_extensions]'] = $allowed_extensions . ' ' . $denied_extensions;
    $this->drupalGet($path);
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()
      ->responseContains($this->t('The list of allowed extensions is not valid, be sure to not include %ext extension(s).', [
        '%ext' => $denied_extensions,
      ]));

    // Single denied extensions entered.
    $denied_extensions = 'doc';
    $edit['settings[minisite_extensions]'] = $allowed_extensions . ' ' . $denied_extensions;
    $this->drupalGet($path);
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()
      ->responseContains($this->t('The list of allowed extensions is not valid, be sure to not include %ext extension(s).', [
        '%ext' => $denied_extensions,
      ]));



    // Multiple denied extensions entered.
    $denied_extensions = 'scr, exe, php, bmp, doc, rtf';
    $edit['settings[minisite_extensions]'] = $allowed_extensions . ' ' . $denied_extensions;
    $this->drupalGet($path);
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()
      ->responseContains($this->t('The list of allowed extensions is not valid, be sure to not include %ext extension(s).', [
        '%ext' => $denied_extensions,
      ]));
  }

}
