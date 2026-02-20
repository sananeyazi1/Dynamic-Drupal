<?php

namespace Drupal\scorm_field\Plugin\rest\resource;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\file\FileInterface;


/**
 * Represents ScormStartSco records as resources.
 *
 * @RestResource (
 *   id = "scorm_field_scormstartsco",
 *   label = @Translation("ScormStartSco"),
 *   uri_paths = {
 *     "canonical" = "/api/scorm-field-scormstartsco/{fid}",
 *   }
 * )
 *
 * @DCG
 * The plugin exposes key-value records as REST resources. In order to enable it
 * import the resource configuration into active configuration storage. An
 * example of such configuration can be located in the following file:
 * core/modules/rest/config/optional/rest.resource.entity.node.yml.
 * Alternatively you can enable it through admin interface provider by REST UI
 * module.
 * @see https://www.drupal.org/project/restui
 *
 * @DCG
 * Notice that this plugin does not provide any validation for the data.
 * Consider creating custom normalizer to validate and normalize the incoming
 * data. It can be enabled in the plugin definition as follows.
 * @code
 *   serialization_class = "Drupal\foo\MyDataStructure",
 * @endcode
 *
 * @DCG
 * For entities, it is recommended to use REST resource plugin provided by
 * Drupal core.
 * @see \Drupal\rest\Plugin\rest\resource\EntityResource
 */
class ScormstartscoResource extends ResourceBase {

  /**
   * The key-value storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    KeyValueFactoryInterface $keyValueFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $keyValueFactory);
    $this->storage = $keyValueFactory->get('scorm_field_scormstartsco');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('keyvalue')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param int $fid
   *   The ID of the file record.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the record.
   */
  public function get($fid) {

    try {

      if (!isset($fid)) {
        $this->logger->warning("fid parameter can't be empty.");
        throw new NotFoundHttpException("fid parameter can't be empty.");        
      }

      $scorm_service = \Drupal::service('scorm_field.scorm');
      $scorm_player = \Drupal::service('scorm_field.scorm_player');

      /** @var \Drupal\file\FileInterface|null $file*/
      $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->load($fid);

      if ($file instanceof FileInterface) {
        $scorm = $scorm_service->scormLoadByFileEntity($file);
      }

      if (isset($scorm)) {
        $start_sco = $scorm_player->getStartSCO($scorm);
      }
      else {
        $this->logger->warning("No valid scorm found.");
        throw new NotFoundHttpException("No valid scorm found."); 
      }

      if (isset($start_sco) && is_object($start_sco)) {
        $element = ['start_sco' => $start_sco->id];
        $response = new ModifiedResourceResponse($element, 201);
      }
      else {
        $this->logger->warning("No valid start sco found.");
        throw new NotFoundHttpException("No valid start sco found."); 
      }
    }
    catch(\Exception $e) {

      $this->logger->warning($e->getMessage());
      $error['error'] = $e->getMessage();
      $response = new ModifiedResourceResponse($error, 400);

    }

    return $response;

  }

}
