<?php

namespace Drupal\Tests\plupload\FunctionalJavascript;

/**
 * Test Plupload Widget.
 *
 * @group plupload
 */
class PluploadWebDriverTest extends PluploadWebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'plupload',
    'plupload_test',
  ];

  /**
   * Permissions for user that will be logged-in for test.
   *
   * @var array
   */
  protected static $userPermissions = [
    'access content',
    'administer site configuration',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $account = $this->drupalCreateUser(static::$userPermissions);
    $this->drupalLogin($account);
  }

  /**
   * Tests the add widget with iframe form.
   */
  public function testUploadFile() {
    $this->drupalGet('plupload-test');

    $web_assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Wait for DOM load finished.
    $web_assert->waitForElementVisible('css', '.plupload_wrapper');
    $web_assert->pageTextContains('Drag files here.');

    // Add test file.
    $this->addFile($this->getTestFilePath('plupload-test-file.zip'));

    // Upload file.
    $web_assert->waitForElementRemoved('css', '.plupload_start.plupload_disabled');
    $page->clickLink('Start Upload');

    // Submit form.
    $web_assert->waitForElementVisible('css', '.plupload_progress');
    $page->pressButton('Submit');

    $web_assert->pageTextContains('Files uploaded correctly: public://plupload-test/plupload-test-file.zip.');
  }

  /**
   * Tests the submit works just submitting the form.
   */
  public function testOnlyPressSubmit() {
    $this->drupalGet('plupload-test');

    $web_assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Wait for DOM load finished.
    $web_assert->waitForElementVisible('css', '.plupload_wrapper');
    $web_assert->pageTextContains('Drag files here.');

    // Add test file.
    $this->addFile($this->getTestFilePath('plupload-test-file.zip'));

    // Submit form.
    $web_assert->waitForElementRemoved('css', '.plupload_start.plupload_disabled');
    $page->pressButton('Submit');

    // Phpunit checks texts before form submit, need to reload the page.
    $this->drupalGet('plupload-test');
    $web_assert->pageTextContains('Files uploaded correctly: public://plupload-test/plupload-test-file.zip.');
  }

}
