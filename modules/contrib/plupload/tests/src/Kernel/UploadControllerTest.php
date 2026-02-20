<?php

namespace Drupal\Tests\plupload\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests PlUpload upload controller.
 *
 * @group plupload
 */
class UploadControllerTest extends KernelTestBase {

  /**
   * Temporary file (location + name).
   *
   * @var string
   */
  protected $tmpFile = '';

  /**
   * Temp dir.
   *
   * @var string
   */
  protected $filesDir = '';

  /**
   * Testfile prefix.
   *
   * @var string
   */
  protected $testfilePrefix = 'pluploadtest_';

  /**
   * Testfile data.
   *
   * @var string
   */
  protected $testfileData = 'Plupload test file data';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['plupload'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('plupload');

    $this->filesDir = $this->siteDirectory . '/files';
    $this->setSetting('file_temp_path', $this->filesDir);

    $this->tmpFile = tempnam('', $this->testfilePrefix);
    file_put_contents($this->tmpFile, $this->testfileData);
  }

  /**
   * Test that Plupload correctly handles uploads.
   */
  public function testUploadController() {

    $this->container->get('router.builder')->rebuild();

    $unicode_emoticon = json_decode('"\uD83D\uDE0E"');

    $tmp_filename = 'o_loremipsum.tmp';

    $controller_result = $this->sendPluploadRequest($this->tmpFile, "{$this->testfilePrefix}controller-Капля   a,A;1{$unicode_emoticon}.jpg", $tmp_filename);

    $this->assertInstanceOf(JsonResponse::class, $controller_result);
    $this->assertEquals(200, $controller_result->getStatusCode());
    $result = json_decode($controller_result->getContent());
    $this->assertIsObject($result);
    $this->assertEquals([
      'jsonrpc' => '2.0',
      'result' => NULL,
      'id' => 'id',
    ], (array) $result);
    $file_uploaded_uri = $this->config('plupload.settings')->get('temporary_uri') . '/' . $tmp_filename;
    $this->assertFileExists($file_uploaded_uri);
    $this->assertEquals(file_get_contents($file_uploaded_uri), $this->testfileData);
  }

  /**
   * Test that Plupload correctly handles uploads in chunks.
   */
  public function testUploadControllerInChunks() {

    $this->container->get('router.builder')->rebuild();

    $unicode_emoticon = json_decode('"\uD83D\uDE0E"');

    $tmp_filename = 'o_loremipsum.tmp';

    foreach (str_split($this->testfileData, 10) as $chunk => $data) {

      // Split the test file into chunks.
      $chunk_name = $this->tmpFile . '-' . $chunk;
      file_put_contents($chunk_name, $data);

      $controller_result = $this->sendPluploadRequest($chunk_name, "{$this->testfilePrefix}controller-Капля   a,A;1{$unicode_emoticon}.jpg", $tmp_filename, $chunk);

      $this->assertEquals(200, $controller_result->getStatusCode());
      $result = json_decode($controller_result->getContent());
      $this->assertIsObject($result);
      $this->assertEquals([
        'jsonrpc' => '2.0',
        'result' => NULL,
        'id' => 'id',
      ], (array) $result);
    }

    $file_uploaded_uri = $this->config('plupload.settings')->get('temporary_uri') . '/' . $tmp_filename;
    $this->assertFileExists($file_uploaded_uri);
    $this->assertEquals(file_get_contents($file_uploaded_uri), $this->testfileData);
  }

  /**
   * Send a Plupload request.
   *
   * @param string $path
   *   The path to the file to upload.
   * @param string $originalName
   *   The original name of the file uploaded file.
   * @param string $tmp_filename
   *   The temporary filename sent by Plupload.
   * @param int|null $chunk
   *   The chunk number, if chunked upload.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The controller response.
   */
  protected function sendPluploadRequest(string $path, string $originalName, string $tmp_filename, int $chunk = NULL): JsonResponse {
    $uploaded_file = new UploadedFile($path, $originalName);
    $file_bag = new FileBag();
    $file_bag->set('file', $uploaded_file);

    $request = new Request();
    $request->request->set('name', $tmp_filename);

    if ($chunk !== NULL) {
      $request->request->set('chunk', $chunk);
    }

    $request->files = $file_bag;
    $request->headers->set('Content-Type', 'multipart/form-data');

    $controller = new TestUploadController($request, $this->container->get('file.htaccess_writer'), $this->container->get('file_system'), $this->container->get('config.factory'));
    return $controller->handleUploads();
  }

}
