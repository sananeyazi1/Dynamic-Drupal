<?php

namespace Drupal\Tests\remote_stream_wrapper_widget\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Remote Stream Wrapper Widget.
 *
 * @group remote_stream_wrapper_widget
 */
class RemoteStreamWrapperWidgetTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'remote_stream_wrapper_widget', 'file'];

  /**
   * The content type.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  private $contentType;

  /**
   * The field storage.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  private $fieldStorage;

  /**
   * The field name.
   *
   * @var string
   */
  private $fieldName;

  /**
   * The field config.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  private $field;

  /**
   * The user.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->contentType = $this->drupalCreateContentType();
    $this->fieldName = strtolower($this->randomMachineName());

    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'type' => 'file',
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => $this->contentType->id(),
    ]);
    $this->field->save();
    \Drupal::service('entity_display.repository')->getFormDisplay('node', $this->contentType->id(), 'default')
      ->setComponent($this->fieldName, [
        'type' => 'remote_stream_wrapper',
      ])
      ->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', $this->contentType->id(), 'full')
      ->setComponent($this->fieldName, [
        'type' => 'file_url_plain',
      ])
      ->save();

    $this->user = $this->drupalCreateUser(['bypass node access']);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests the basic functionality of the Remote Stream Wrapper Widget.
   */
  public function testBasicFunctionality() {
    $nodeTitle = $this->randomString();
    $values = [
      'title[0][value]' => $nodeTitle,
      "{$this->fieldName}[0][url]" => 'http://example.com/test.txt',
    ];
    $this->getSession()->visit("/node/add/{$this->contentType->id()}");
    $this->assertSame(200, $this->getSession()->getStatusCode());
    $this->submitForm($values, 'Save');

    $this->assertSame(200, $this->getSession()->getStatusCode());
    $this->assertSession()->pageTextContains($nodeTitle);
    $this->assertSession()->pageTextContains($this->fieldName);
    $this->assertSession()->pageTextContains('http://example.com/test.txt');
  }

  /**
   * Tests that the site does not crash when the field is left empty.
   */
  public function testRegression3043148() {
    $nodeTitle = $this->randomString();
    $values = [
      'title[0][value]' => $nodeTitle,
      "{$this->fieldName}[0][url]" => '',
    ];
    $this->getSession()->visit("/node/add/{$this->contentType->id()}");
    $this->assertSame(200, $this->getSession()->getStatusCode());
    $this->submitForm($values, 'Save');

    $this->assertSame(200, $this->getSession()->getStatusCode());
    $this->assertSession()->pageTextContains($nodeTitle);
    $this->assertSession()->pageTextNotContains($this->fieldName);
  }

}
