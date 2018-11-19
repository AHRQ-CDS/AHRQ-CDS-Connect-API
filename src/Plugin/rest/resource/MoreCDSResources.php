<?php
/**
 * This file contains the GET and PATCH methods for /cds_api.
 * It is needed because PHP requires that the `/cds_api/` for POST
 * be implemented separately from the `/cds_api/{id}` for GET and PATCH.
 *
 * POST is implemented in CDSResources.php, while GET and PATCH are implemented here.
 * Common code for the 3 methods are in CDSResrouce.php
 */
namespace Drupal\cds_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Psr\Log\LoggerInterface;
use JsonSchema\Validator;
use Drupal\Component\Utility\Xss;

use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSUnknownNodeIdException;
use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSNodeIdRequiredException;
use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSNotAuthorizedException;

/**
 * Provides a custom CDS resource interface for GET and PATCH /cds_api/{id}
 *
 * This Drupal 8 Resource implements the GET and PATCH methods for /cds_api/{id}
 *
 * @RestResource(
 *   id = "more_cds_resources",
 *   label = @Translation("More CDS Resources"),
 *   uri_paths = {
 *     "canonical" = "/cds_api/{id}"
 *   }
 * )
 */
class MoreCDSResources extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;


  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
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
      $container->get('logger.factory')->get('ccms_rest'),
      $container->get('current_user')
    );
  }


/**
 * Responds to entity GET /cds_api/{id} requests.
 *
 * @param \Drupal\node\NodeInterface $artifact
 *   The entity object.
 *
 * @return \Drupal\rest\ResourceResponse
 *   The response containing the entity with its accessible fields as a CDS JSON document
 *
 * @throws CDSUnknownNodeIdException
 * @throws CDSNodeIdRequiredException
 */
  public function get($id = NULL) {

    if ($id) {
      // Load the node with that id
      $loaded_node = Node::load($id);

      if (!empty($loaded_node)) {
        $artifact = new CDSArtifact();
        $artifact->load_node($loaded_node);
        // convert loaded artifact to json
        $json = $artifact->get_as_assoc_array();
        // return as a response resource
        $response = new ResourceResponse($json,200);
        return $response;
      } else {
        throw new CDSUnknownNodeIdException( $id );
      }
    } else {
      // @todo: this is never reached because it is intercepted first by the valid /cds_api which returns the schema
      throw new CDSNodeIdRequiredException();
    }
  }


  /**
   * Responds to PATCH /cds_api/{id} requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *     The response containing the modified entity as a CDS JSON document
   *
   * @throws CDSNotAuthorizedException
   * @throws CDSUnknownNodeIdException
   * @throws CDSNodeIdRequiredException
   */
  public function patch($id) {
    $decoded_payload = json_decode(file_get_contents('php://input'),true);

    if ($id) {
      // Load the node with that id
      $loaded_node = Node::load($id);

      if (!empty($loaded_node)) {
        // Check if current user has permission to update this node.
        $user = \Drupal\user\Entity\User::load($this->currentUser->id());
        $check = $loaded_node->access('update', $user);
        if ($check) { // user is allowed to update the artifact
          $updated_artifact = new CDSArtifact();
          $updated_artifact->load_json(json_decode(json_encode($decoded_payload)));
          $updated_artifact->update_node($loaded_node);
          // return the updated artifact as json
          $updated_artifact->load_node($loaded_node);
          $tmp = $updated_artifact->get_as_assoc_array();
          return new ResourceResponse( $tmp );
        } else {
          // @todo this is never reached because it is intercepted first by the Drupal/PHP handler
          throw new CDSNotAuthorizedException( $id );
        }
      } else {
        throw new CDSUnknownNodeIdException( $id );
      }
    } else {
      // @todo: this is never reached because it is intercepted first by the not valid PATCH /cds_api
      throw new CDSNodeIdRequiredException();
    }
  }
}
