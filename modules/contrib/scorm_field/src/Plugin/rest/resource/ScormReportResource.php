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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\scorm_field\ScormFieldCommonService;

/**
 * Represents Scorm Report records as resources.
 *
 * @RestResource (
 *   id = "scorm_field_scorm_report",
 *   label = @Translation("Scorm Report"),
 *   uri_paths = {
 *     "canonical" = "/api/scorm-field-scorm-report/{id}",
 *     "create" = "/api/scorm-field-scorm-report"
 *   }
 * )
 */
class ScormReportResource extends ResourceBase {

  /**
   * The key-value storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $storage;

  /**
   * ScormFieldCommonService
   */
  protected $scormFieldCommonService;  

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
    ScormFieldCommonService $scorm_field_common_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $keyValueFactory, $scorm_field_common_service);
    $this->storage = $keyValueFactory->get('scorm_field_scorm_report');
    $this->scormFieldCommonService = $scorm_field_common_service;
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
      $container->get('keyvalue'),
      $container->get('scorm_field.common_service')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param int $id
   *   The ID of the record.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the record.
   */
  public function get($id) {

    $response = [];

    try {

      $reports = $this->scormFieldCommonService->getScormReportByNodeId($id);

      if (!empty($reports)) {
        
        if ($this->scormFieldCommonService->checkViewReportAccess($id)) {
          $response['status'] = 'success';
          $response['action'] = 'scorm_report';
          $response['object'] = $reports;  
          $response = new ModifiedResourceResponse($response, 201);
        }
        else {
           throw new AccessDeniedHttpException("Access Denied");
        }

      }      
      else {
        throw new NotFoundHttpException("The nid does not deliver any data.");
      }
    }
    catch(\BadRequestHttpException $e) {

      $this->logger->warning($e->getMessage());
      $error['error'] = $e->getMessage();
      $response = new ModifiedResourceResponse($error, 400);      

    }
    catch(\EntityStorageException $e) {

      $this->logger->warning($e->getMessage());
      $error['error'] = $e->getMessage();
      $response = new ModifiedResourceResponse($error, 404);
          
    }
    catch(\Exception $e) {

      $this->logger->warning($e->getMessage());
      $error['error'] = $e->getMessage();
      $response = new ModifiedResourceResponse($error, 404);

    }   
    
    return $response;

  }

 
}
