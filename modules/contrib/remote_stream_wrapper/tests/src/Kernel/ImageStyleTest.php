<?php

namespace Drupal\Tests\remote_stream_wrapper\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\remote_stream_wrapper\Entity\ImageStyle;

/**
 * Tests for Remote Stream Wrapper ImageStyle entity.
 *
 * @coversDefaultClass \Drupal\remote_stream_wrapper\Entity\ImageStyle
 * @group remote_stream_wrapper
 */
class ImageStyleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'image',
    'remote_stream_wrapper',
  ];

  /**
   * A testing image style entity.
   *
   * @var \Drupal\image\ImageStyleInterface
   */
  protected $imageStyle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->imageStyle = ImageStyle::create([
      'name' => 'test',
      'label' => 'Test',
    ]);
    $this->imageStyle->save();

    // KernelTestBase sets the default scheme to public, which overrides
    // our test setup.
    // @see \Drupal\KernelTests\KernelTestBase::bootKernel()
    unset($GLOBALS['config']['system.file']);
  }

  /**
   * Test that when we don't set a default scheme, public is used.
   */
  public function testBuildUriDefault() {
    $this->config('system.file')->set('default_scheme', 'public')->save();
    $uri = $this->imageStyle->buildUri('https://www.example.com/123.jpg');
    $this->assertEquals('public://styles/test/https/www.example.com/123.jpg', $uri);
  }

  /**
   * Test that when we set the default scheme to foobar, that it is used.
   */
  public function testBuildUriWithDummySchema() {
    $this->config('system.file')->set('default_scheme', 'foobar')->save();
    $uri = $this->imageStyle->buildUri('https://www.example.com/123.jpg');
    $this->assertEquals('foobar://styles/test/https/www.example.com/123.jpg', $uri);
  }

}
