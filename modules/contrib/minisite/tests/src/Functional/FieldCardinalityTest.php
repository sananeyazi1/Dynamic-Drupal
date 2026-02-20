<?php

namespace Drupal\Tests\minisite\Functional;

/**
 * Tests the minisite field cardinality.
 *
 * @group minisite
 */
class FieldCardinalityTest extends MinisiteTestBase {

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
   * Tests that only cardinality 1 is allowed.
   */
  public function testCardinality() {
    $type_name = $this->contentType;

    $field_name = 'ms_fn_' . strtolower($this->randomMachineName(4));
    $field_label = 'ms_fl_' . strtolower($this->randomMachineName(4));

    $initial_edit = [
      'new_storage_type' => 'minisite',
      'label' => $field_label,
      'field_name' => $field_name,
    ];

    $this->drupalGet("admin/structure/types/manage/$type_name/fields/add-field");
    $this->submitForm($initial_edit, 'Continue');
    $this->assertSession()->pageTextContains('This field cardinality is set to 1 and cannot be configured.');
  }

}
