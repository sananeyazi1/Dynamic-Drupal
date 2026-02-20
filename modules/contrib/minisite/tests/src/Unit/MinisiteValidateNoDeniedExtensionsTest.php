<?php

declare(strict_types=1);

namespace Drupal\Tests\minisite\Unit;

use Drupal\Core\Form\FormState;
use Drupal\minisite\Plugin\Field\FieldType\MinisiteItem;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for the validateNoDeniedExtensions method in the Minisite module.
 *
 * @group minisite
 * @package Drupal\minisite\Tests
 */
final class MinisiteValidateNoDeniedExtensionsTest extends UnitTestCase {

  /**
   * Tests the validateNoDeniedExtensions method.
   */
  public function testValidateNoDeniedExtensions() {
    // Create a FormState object.
    $form_state = new FormState();

    // Create the element with some test value.
    $element = [
      '#value' => 'jpg doc exe php',
      '#parents' => ['minisite_extensions'],
    ];

    MinisiteItem::validateNoDeniedExtensions($element, $form_state);
    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors, 'Errors were expected but none found.');
    $this->assertStringContainsString('doc, exe, php', $errors['minisite_extensions']->getArguments()['%ext']);

    // Clear the errors for the next test.
    $form_state->clearErrors();

    // Test with the environment variable set.
    putenv('MINISITE_DENIED_EXTENSIONS=jpg');
    MinisiteItem::validateNoDeniedExtensions($element, $form_state);
    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors, 'Errors were expected but none found.');
    $this->assertStringContainsString('jpg', $errors['minisite_extensions']->getArguments()['%ext']);

    // Clear the errors for the next test.
    $form_state->clearErrors();

    // Test with the environment variable not set.
    putenv('MINISITE_DENIED_EXTENSIONS');
    MinisiteItem::validateNoDeniedExtensions($element, $form_state);
    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors, 'Errors were expected but none found.');
    $this->assertStringContainsString('doc, exe, php', $errors['minisite_extensions']->getArguments()['%ext']);
  }

}
