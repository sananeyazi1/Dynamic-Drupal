<?php

namespace Drupal\Tests\system_stream_wrapper\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests library discovery functions.
 *
 * @group system_stream_wrapper
 */
class LibraryDiscoveryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'system_stream_wrapper'];

  /**
   * Tests loading a library that does not exist.
   */
  public function testLibraryLoad() {
    $this->assertFalse(file_exists('library://foo'));
  }

}
