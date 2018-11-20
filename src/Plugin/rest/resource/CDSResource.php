<?php
namespace Drupal\cds_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Psr\Log\LoggerInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Drupal\Component\Utility\Xss;


/**
 * Provides a custom CDS resource interface
 * @note if we change any of the uri_paths below, be sure
 *  to also change get_uri_path()
 *
 * @RestResource(
 *   id = "cds_resource",
 *   label = @Translation("CDS Resource"),
 *   uri_paths = {
 *     "canonical" = "/cds_api",
 *     "create" = "/cds_api"
 *   }
 * )
 */
class CDSResource extends ResourceBase {
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
   * returns the URI of resources in this API
   *
   * @todo: hard-coded path, would be better if full path or retrieved from the uri-paths in @RestResource
   */
  public static function get_uri_path() {
    return "/cds_api";
  }


  /**
   * Responds to entity GET /cds_api requests.
   *
   * @return \Drupal\rest\ResourceResponse the CDS JSON schema
   */
  public function get() {
    $response = json_decode(json_encode(CDSArtifact::get_schema()), true);
    return new ResourceResponse($response);
  }

  /**
   * Responds to POST /cds_api/ requests.
   *
   * @param $payload A CDS JSON Schema compliant array representation of the request payload
   *    defining the artifact to be added
   *
   *    Note that the `"meta"` property should not be specified since this method will
   *    fill that in once the data is saved to the database.
   *
   * Returns a list of bundles for specified entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *    The JSON representation of the saved artifactk.  Note that the Node ID will be specified
   *    in the response.
   *
   * @throws AccessDeniedHttpException
   *
   * @todo need to change exceptions to do CDS-specific JSON exception messages
   */
  public function post( $payload ) {
    if (!$this->currentUser->hasPermission('create artifact content')) {
      // @todo the message that is actually sent is the default message, different than specified here
      throw new AccessDeniedHttpException("Only authorized users can modify the repository.");
    }

    // this block remains untested; it was here because without it, a JSON document of "{}" did not work,
    // but that is no longer the case.
    // "normal" processing is single object, in the else statement
    // if (is_array($payload)) {
    //   $created_nodes = [];
    //   foreach ($payload as $element) {
    //     try {
    //       $tmp = new CDSArtifact();
    //       $tmp->load_json($element);
    //       $tmp->get_as_node();
    //       $created_nodes[] = $tmp->get_title();
    //     } catch (\Exception $e) {
    //       //if for some reason the node cant be created, we attempt to explain why
    //       $created_nodes[] = "Failed to create one Node due to Exception: " . $e->getMessage();
    //     }
    //   }
    //   $response = ['nodes_created' => $created_nodes];
    //   return new ResourceResponse($response);
    // } //else { // payload is a single artifact
      $artifact = new CDSArtifact();
      $artifact->load_json( $payload );
      $artifact_node = $artifact->get_as_node();  // @todo node was saved! should really do this separately from a "get" function
      $artifact->load_node( $artifact_node );
      return new ResourceResponse( $artifact->get_as_assoc_array() );
    // }
  }


  // ----- Validation and Sanitization utility methods -----------------------------------


  /**
   * Sanitizes the string; if $permissive is true, then sanitize less aggressively
   *    which is useful for rich text, whereas the default (false) is more useful
   *    for plain text.  An example of the difference is:
   *    sanitize_string( "<h1>abc</h1>" ) => "abc"
   *    sanitize_string( "<h1>abc</h1>", true ) => "<h1>abc</h1>"
   *
   * @param string $string
   *    The string to sanitize
   *
   * @param bool permissive
   *    Set this to true for Rich text fields, and false (default) for plain text fields
   *
   * @return the sanitized string
   */
  public static function sanitize_string( $string, bool $permissive=false ): string {
    if ( $string === null || $string === "" ) {
      return "";
    }
    else {
      if ( $permissive ) {
        return XSS::filterAdmin( $string );
      }
      else {
        return XSS::filter( $string );
      }
    }
  }


  /**
   * utility to validate a json object against the CDS schema,
   *
   * @param mixed $json the decoded JSON object
   *
   * @param bool $applyDefaults
   *    Boolean to specify whether to apply the defaults specified in the CDS JSON Schema
   *    if a value is not specified.
   *
   * @return the Validator object used to valiate so that any errors can be processed/noted
  */
  public static function validate_json( $json, bool $applyDefaults=true ): Validator
  {
      $schema = CDSArtifact::get_schema();
      $validator = new Validator;
      $constraints = $applyDefaults ? Constraint::CHECK_MODE_APPLY_DEFAULTS : Constraint::CHECK_MODE_NONE;
      $validator->validate( $json, $schema, $constraints );
      return $validator;
  }


    /**
     * returns the validation error object as an array of errors
     *      of the form [key] => message
     *
     * @param $validator
     *    The validator that was used for validation
     *
     * @returns the validation error array
     */
    public static function get_schema_validation_errors_as_array( $validator )
    {
        $retval = [];
        foreach ($validator->getErrors() as $error) {
            $retval[$error['property']] = $error['message'];
        }
        return $retval;
    }


    /**
     * returns all the validation errors in the validator error object as a string
     *      useful for printing out the errors in phpunit assert statements
     *
     * @param $validator
     *    The validator that was used for validation
     *
     * @returns the validation error array
     */
    public static function get_schema_validation_errors_as_string( $validator )
    {
        $retval = "";
        foreach ($validator->getErrors() as $error) {
            $retval = $retval . sprintf("[%s] %s\n", $error['property'], $error['message']);
        }
        return $retval;
    }
}
