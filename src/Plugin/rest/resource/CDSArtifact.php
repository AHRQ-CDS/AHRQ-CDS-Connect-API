<?php
/**
 * This file implements the logical CDS Artifact class to simplify and correctly
 * maintain them in the larger and more generci Drupal framework.
 *
 * Note that sonarcube has noted that this file is too large, and we have started
 * to separate the logic to enable refactoring them into subcomponent classes.  The resulting
 * classes will be
 *    CDSArtifact - this class
 *    CDSNodeUtils - helper utilities to work with Drupal nodes
 *    CDSTaxonomyUtils - helper utilities to work with Drupal taxonomies
 *    CDSParagraphUtils - helper utilities to work with Drupal paragraphs
 */
namespace Drupal\cds_api\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\Entity\Paragraph;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSNonconformantJsonException;
use Psr\Log\LoggerInterface;

/**
 * The main CDS Artifact logical class
 *
 * This class encapsulates a CDS-centric view of CDS Artifacts, abstracting away the
 * mechanisms of Drupal 8 and input/output to JSON,
 * to make writing CDS-specific code easier.
 */
class CDSArtifact {

  // ----- Private properties to represent the CDS Schema ----- ----- ----- ----- ----- -----

  // Drupal node-specific
  private $node_id; // read-only, required by all REST API except POST where it is generated

  private $title;//reqd
  private $description;
  //Metadata
  private $identifier;
  private $version;//reqd
  private $status;//reqd
  private $experimental;
  private $artifact_type;//reqd
  private $creation_date;
  // Artifact Creation and Usage
    private $license;
    private $copyrights;
    private $keywords;
    private $steward;//nodes
    private $publisher;//nodes
    private $contributors;
    private $ip_attestation;
  // Artifact Organization
    private $mesh_topics; // taxonomy term
    private $knowledge_level;
    private $related_artifacts;//nodes
  //Representation
    private $triggers;
    private $inclusions;
    private $exclusions;
    private $interventions_and_actions;
    private $logic_files;//files
  //Implementation Details
    private $engineering_details;
    private $technical_files;//files
    private $miscellaneous_files;//files
  //Purpose and Usage
    private $purpose;
    private $intended_population;
    private $usage;
    private $cautions;
    private $test_patients;//files
  //Supporting Evidence
    private $source_description;
    private $source;
    private $references;
    private $artifact_decision_notes;
    // Recommendation Statements
    private $recommendation_statement;
  //Repository Information
    private $approval_date;
    private $expiration_date;
    private $last_review_date;
    private $publication_date;
    private $preview_image;//file
  //Testing Experience
    private $pilot_experience;
  // Coverage Requirements Discovery (CRD)
    private $payer;
    private $code_system;
    private $electronic_prescribing_code;


  // ----- Arrays to categorize schema properties ----- ----- ----- ----- ----- ----- -----


  private $non_rich_text_fields =
    array(
      'node_id',
      'title','identifier',
      'version','experimental','creation_date',
      'status','artifact_type',
      'keywords','steward','publisher','license',
      'ip_attestation',
      'mesh_topics','knowledge_level','related_artifacts',
      'logic_files',
      'technical_files','miscellaneous_files',
      'test_patients',
      'source', 'approval_date',
      'expiration_date','last_review_date','publication_date',
      'preview_image', 'payer', 'code_system', 'electronic_prescribing_code'
    );

  private $rich_text_fields =
    array(
      'copyrights','contributors','description',
      'inclusions','exclusions','interventions_and_actions','triggers',
      'engineering_details',
      'intended_population','purpose','usage','cautions',
      'artifact_decision_notes','source_description','references',
      'recommendation_statement', 'pilot_experience'
    );

  private $all_fields = null;

  private $embedding_objects = array(
    'organization',
    'creation_and_usage',
    'artifact_representation',
    'implementation_details',
    'purpose_and_usage',
    'supporting_evidence',
    'repository_information',
    'testing_experience',
    'coverage_requirements_discovery'
  );

  private $valid_paragraph_types = array(
    'artifact_representation',
    'recommendation_statement',
    'implementation_details',
    'purpose_and_usage',
    'supporting_evidence',
    'repository_information',
    'testing_experience'
  );


  // ----- CDS Artifact static methods ----- ----- ----- ----- ----- -----


  /**
   *  Retrieves and returns the official CDS JSON Schema
   *
   *  @return the CDS JSON Schema
   */
  public static function get_schema() {
    $jsonstr = file_get_contents( __DIR__ . "/cds_schema.json" );
    return json_decode( $jsonstr );
  }


  // ----- CDS Artifact constructor and initializer methods ----- ----- ----- ----- ----- -----


  /** constructs an empty CDSArtifact object
   *
   */
  public function __construct() {
    // setup static properties that requires functions
    $this->all_fields = array_merge( $this->non_rich_text_fields, $this->rich_text_fields );
  }


  /**
   * Sets the CDSArtifact object's properties based on a JSON specification that conforms
   * to the CDS JSON schema
   *
   * @param $json the JSON object can be represented as
   *          a string, an array, or decoded JSON (using PHP's json_decode())
   *
   * @param bool $assign_defaults Boolean value that if set to true (default) will assign
   *      default values as defined by the CDS Schema (in the special case of the title,
   *      default to "CDS Artifact uploaded by <username> on <timestamp>")
   *
   * @throws CDSNonconformantJsonException
   */
  public function load_json( $json, bool $assign_defaults = true ) {
    // make $json something consistent and manipulatable
    if ( is_string( $json ) ) {
      $json = json_decode( $json );
    }
    if ( !is_array( $json ) ) {
      $json = (array) $json;
    }
    if ( $assign_defaults && !array_key_exists( "title", $json ) ) {
      $json['title'] = "CDS Artifact uploaded on " . date("D, M d, Y");
    }
    $json = json_decode( json_encode( $json ) );

    $validator = CDSSchema::validate_json( $json, $assign_defaults );
    if ( !$validator->isValid() ) {
      $error = CDSSchema::get_schema_validation_errors_as_string( $validator );
      // die(print_r($error));
      throw new CDSNonconformantJsonException( $error );
    }

    // Sanitize input.
    foreach ($json as $key=>$value) {
      if (in_array($key, $this->rich_text_fields)) { // rich text fields
        $this->$key = CDSSchema::sanitize_string( $value, true );
      } elseif (in_array($key, $this->non_rich_text_fields)) { // UTF8 text fields
        $this->$key = CDSSchema::sanitize_string( $value );
      } elseif (in_array($key, $this->embedding_objects)) {
        $this->get_embedded_object($value); // embedding objects need to be unpacked
      }
    }

    // Validate and assign title.
    if ( isset($json->title) ) {
      $this->set_title( $json->title );
    } elseif ( isset($json->name) ) {
      $this->set_title( $json->name );
    }
  }

  /**
   * Sets the CDSArtifact object's properties based on Drupal 8's node object (from database)
   *
   * @param $node
   *    The Drupal 8 node object
   *
   * @throws CDSNonconformantJsonException
   *
   * @todo needs kernel testing
   */
  public function load_node($node) {
    // Takes a node and sets the artifact fields

    // Fields with simple values (e.g., string).
    $this->set_value( 'node_id',        $this->node_get_value( $node, "nid" ) );
    $this->set_value( 'title',          $this->node_get_value( $node, "title" ) );
    $this->set_value( 'description',    $this->node_get_value( $node, "field_description" ) );
    $this->set_value( 'identifier',     $this->node_get_value( $node, "field_identifier" ) );
    $this->set_value( 'version',        $this->node_get_value( $node, "field_version" ) );
    $this->set_value( 'experimental',   (boolean) $this->node_get_value( $node, 'field_experimental' ) );
    $this->set_value( 'creation_date',  $this->node_get_value( $node, 'field_creation_date' ) );
    $this->set_value( 'copyrights',     $this->node_get_value( $node, 'field_copyrights' ) );
    $this->set_value( 'contributors',   $this->node_get_value( $node, 'field_contributors' ) );
    $this->set_value( 'ip_attestation', (boolean) $this->node_get_value( $node, 'field_ip_attestation' ) );
    $this->set_value( 'payer', $this->node_get_value( $node, 'field_payer' ) );
    $this->set_value( 'code_system', $this->node_get_value( $node, 'field_code_system' ) );
    $this->set_value( 'electronic_prescribing_code', $this->node_get_value( $node, 'field_erx_code' ) );

    // Fields which reference at most one taxonomy term.
    $this->set_value( 'status',           $this->node_get_value( $node, 'field_status' ) );
    $this->set_value( 'artifact_type',    $this->node_get_value( $node, 'field_artifact_type' ) );
    $this->set_value( 'license',          $this->node_get_value( $node, 'field_license' ) );
    $this->set_value( 'knowledge_level',  $this->node_get_value( $node, 'field_knowledge_level' ) );

    // Fields that potentially reference more than one taxonomy term.
    $this->set_value( 'keywords',  $this->node_get_value( $node, 'field_keywords' ) );
    $this->set_value( 'mesh_topics',  $this->node_get_value( $node, 'field_mesh_topics' ) );

    // Fields which reference one or more nodes.
    $this->set_value( 'related_artifacts',  $this->node_get_value( $node, 'field_related_artifacts' ) );
    $this->set_value( 'steward' ,  $this->node_get_value( $node, 'field_steward' ) );
    $this->set_value( 'publisher' ,  $this->node_get_value( $node, 'field_publisher' ) );

    // All other fields are contained in paragraphs.
    // Artifact representation
    $para = $this->node_get_value( $node, 'field_artifact_representation' );
    if (!empty($para)) {
      isset($para['field_exclusions']) ? $this->set_value( 'exclusions', $para['field_exclusions'] ) : NULL;
      isset($para['field_inclusions']) ? $this->set_value( 'inclusions', $para['field_inclusions'] ) : NULL;
      isset($para['field_interventions_and_actions']) ? $this->set_value( 'interventions_and_actions', $para['field_interventions_and_actions'] ) : NULL;
      isset($para['field_triggers']) ? $this->set_value( 'triggers', $para['field_triggers'] ) : NULL;
      isset($para['field_logic_files']) ? $this->set_value( 'logic_files', empty($para['field_logic_files']) ? [""] : array_column(array_column($para['field_logic_files'], 'url'), 'value')) : NULL;
    }
    // Implementation details
    $para = $this->node_get_value( $node, 'field_implementation_details' );
    if (!empty($para)) {
      isset($para['field_engineering_details']) ? $this->set_value( 'engineering_details', $para['field_engineering_details'] ) : NULL;
      isset($para['field_technical_files']) ? $this->set_value( 'technical_files', empty($para['field_technical_files']) ? [""] : array_column(array_column($para['field_technical_files'], 'url'), 'value')) : NULL;
      isset($para['field_miscellaneous_files']) ? $this->set_value( 'miscellaneous_files', empty($para['field_miscellaneous_files']) ? [""] : array_column(array_column($para['field_miscellaneous_files'], 'url'), 'value')) : NULL;
    }
    // Purpose and usage
    $para = $this->node_get_value( $node, 'field_purpose_and_usage' );
    if (!empty($para)) {
      isset($para['field_cautions']) ? $this->set_value( 'cautions', $para['field_cautions'] ) : NULL;
      isset($para['field_intended_population']) ? $this->set_value( 'intended_population', $para['field_intended_population'] ) : NULL;
      isset($para['field_purpose']) ? $this->set_value( 'purpose', $para['field_purpose'] ) : NULL;
      isset($para['field_usage']) ? $this->set_value( 'usage', $para['field_usage'] ) : NULL;
      isset($para['field_test_patients']) ? $this->set_value( 'test_patients', empty($para['field_test_patients']) ? [""] : array_column(array_column($para['field_test_patients'], 'url'), 'value')) : NULL;
    }
    // Supporting evidence
    $para = $this->node_get_value( $node, 'field_supporting_evidence' );
    if (!empty($para)) {
      isset($para['field_source_description']) ? $this->set_value( 'source_description', $para['field_source_description'] ) : NULL;
      if(isset($para['field_source'])) {
        $this->set_value( 'source', $para['field_source'][0]->title->value );
      }
      isset($para['field_references']) ? $this->set_value( 'references', $para['field_references'] ) : NULL;
      isset($para['field_artifact_decision_notes']) ? $this->set_value( 'artifact_decision_notes', $para['field_artifact_decision_notes'] ) : NULL;
      // Recommendation statements
      $rs_para = isset($para['field_recommendation_statement']) ? $para['field_recommendation_statement'] : [];
      $recommendation_statement_array = [];
      foreach ($rs_para as $rs) {
        $recommendation_statement_array[] = [
          'recommendation' => $rs->get('field_recommendation')->value,
          'strength_of_recommendation' => $rs->get('field_strength_of_recommendation')->value,
          'quality_of_evidence' => $rs->get('field_quality_of_evidence')->value,
          'decision_notes' => $rs->get('field_decision_notes')->value
        ];
      }
      $this->set_value( 'recommendation_statement' , $recommendation_statement_array );
    }
    // Repository information
    $para = $this->node_get_value( $node, 'field_repository_information' );
    if (!empty($para)) {
      isset($para['field_approval_date']) ? $this->set_value( 'approval_date', $para['field_approval_date'] ) : NULL;
      isset($para['field_expiration_date']) ? $this->set_value( 'expiration_date', $para['field_expiration_date'] ) : NULL;
      isset($para['field_last_review_date']) ? $this->set_value( 'last_review_date', $para['field_last_review_date'] ) : NULL;
      isset($para['field_publication_date']) ? $this->set_value( 'publication_date', $para['field_publication_date'] ) : NULL;
    }
    // Testing experience
    $para = $this->node_get_value( $node, 'field_testing_experience' );
    if (!empty($para)) {
      $this->set_value( 'pilot_experience', $para['field_pilot_experience'] );
    }

  }

  // ----- CDS Artifact accessors and mutators ----- ----- ----- ----- ----- -----


  /**
   * set the CDSArtifact title
   *
   * @param string $title
   *    The title of this artifact
   */
  public function set_title( $title ) {
    $this->title = CDSSchema::sanitize_string( $title );
  }


  /**
   * return the CDSArtifact title (special case of get_value("title") to complement set_title )
   *
   * @return The title of this artifact
   */
  public function get_title() {
    return $this->title;
  }


  /**
   * Return the value of a named (by key) property.
   *    - Note that this function does not differentiate
   *          between an undefined key or an unassigned key
   *    - Note that this function may return a string,
   *          boolean, or an object)
   *
   * @param string $key - the name of the property (e.g., `title`)
   *
   * @return
   *    The value of the property or null if it is not defined.
   */
  public function get_value( string $key ) {
    if ( in_array( $key, $this->all_fields ) ) {  // if a valid key
      return $this->$key;
    } else {  // not a valid key
      // @todo currently we silent return a null
      return null;
    }
  }


  /**
   * Set the value of a named (by key) property.
   *    - Note that this function does not differentiate
   *          between an undefined key or an unassigned key
   *    - Note that this function may return a string,
   *          boolean, or an object)
   *
   * @param string $key - the name of the property (e.g., `title`)
   *
   * @param mixed $value - the value to set the property (e.g., "The title")
   *
   * @return the value of the property or null if it is not defined
   */
  public function set_value( $key, $value ) {
    if ( in_array( $key, $this->all_fields ) ) {  // if a valid key
      $this->$key = $value;
      return $this->get_value( $key );
    } else {  // not a valid key
      // @todo currently we silently return a null
      return null;
    }
  }


  /**
   *  Set the value of a property that is embedded inside a parent property.
   *
   *  Set the value of a property that is embedded inside a parent property, for example,
   *  `license` is embedded inside of `creation_and_usage`
   *    - Note that this function does not differentiate
   *          between an undefined key or an unassigned key
   *
   * @param string $outerkey - the name of the "parent" property (`creation_and_usage` in the above example)
   *
   * @param mixed $inner_object - the array of properties (`license` would be one of the properties in the example above)
   *
   * @todo Need to rename this function to set_embedded_object()
   *
   */
  private function get_embedded_object($inner_object) {

    // Loop over the elements of the parent property
    foreach ($inner_object as $key=>$value) {

      // For each element, check to see whether it is rich text or not
      //  so that it can set the appropriate level of sanitization
      $permissive = false;
      if (in_array ($key,$this->rich_text_fields)) {
        $permissive = true;
      }

      // If the element is an array, loop over its elements and sanitize each one.
      if (is_array($value)) {
        $string_array = [];
        foreach($value as $val) {
          if (is_object($val)) { // Element is an object we must unpack
            $tmp_object = new \stdClass();
            foreach ($val as $k=>$v) {
              $tmp_object->$k = CDSSchema::sanitize_string( $v , $permissive);
            }
            $string_array[] = $tmp_object;
          } else { // Element is not an object
            $string_array[] = CDSSchema::sanitize_string( $val , $permissive);
          }
        }
        $this->$key = $string_array;
      } else { // element is not array; sanitize its single value.
        $this->$key = CDSSchema::sanitize_string( $value , $permissive );
      }
    }
  }


  // ----- Outputers and formatters ----- ----- ----- ----- -----


  /**
   * Return the CDSArtifact as an associated array.
   *
   * Organizes and returns the private properties as an associated array.
   *
   * Note this is the first step in outputing JSON,
   *    since when we return a JSON in the REST API, we use PHP's
   *    automatic array->JSON converter
   *
   * @return
   *    CDSArtifact as an associated array
   */
  public function get_as_assoc_array() {
    // note that in the mapping assignments below,
    // some values in this class are single values but stored in an array
    // (e.g., status), and so it is assigned as $this->property[0]
    return [
      'meta' => [
        'node_id' => $this->node_id,
        'self' => CDSResource::get_uri_path() ."/". $this->node_id
      ],
      'title' => $this->title,
      'description' => $this->description,
      'identifier' => $this->identifier,
      'version' => $this->version,
      'status' => $this->status[0],
      'experimental' => $this->experimental,
      'artifact_type' => $this->artifact_type[0],
      'creation_date' => $this->creation_date,
      'creation_and_usage' => [
        'license' => $this->license[0],
        'copyrights' => $this->copyrights,
        'keywords' => $this->keywords,
        'steward' => $this->steward,
        'publisher' => $this->publisher,
        'contributors' => $this->contributors,
        'ip_attestation' => $this->ip_attestation
      ],
      'organization' => [
        'mesh_topics' => $this->mesh_topics,
        'knowledge_level' => $this->knowledge_level[0],
        'related_artifacts' => $this->related_artifacts
      ],
      'artifact_representation' => [
        'triggers' => $this->triggers,
        'inclusions' => $this->inclusions,
        'exclusions' => $this->exclusions,
        'interventions_and_actions' => $this->interventions_and_actions,
        'logic_files' => $this->logic_files
      ],
      'implementation_details' => [
        'engineering_details' => $this->engineering_details,
        "technical_files" => $this->technical_files,
        "miscellaneous_files" => $this->miscellaneous_files,
      ],
      'purpose_and_usage' => [
        'purpose' => $this->purpose,
        'intended_population' => $this->intended_population,
        'usage' => $this->usage,
        'cautions' => $this->cautions,
        "test_patients" => $this->test_patients
        ],
      'supporting_evidence' => [
        'source_description' => $this->source_description,
        'source' => $this->source,
        'references' => $this->references,
        'artifact_decision_notes' => $this->artifact_decision_notes,
        'recommendation_statement' => $this->recommendation_statement,
      ],
      'repository_information' => [
        'approval_date' => $this->approval_date,
        'expiration_date' => $this->expiration_date,
        'last_review_date' => $this->last_review_date,
        'publication_date' => $this->publication_date,
        "preview_image" => $this->preview_image
      ],
      'testing_experience' => [
        'pilot_experience' => $this->pilot_experience
      ],
      'coverage_requirements_discovery' => [
        'payer' => $this->payer,
        'code_system' => $this->code_system,
        'electronic_prescribing_code' => $this->electronic_prescribing_code
      ]
    ];
  }


  /**
   *  Return a Drupal 8 node representation initialized to the data in this object
   *
   * @todo the save() portion of this code should be refactored out to a separate function when
   *    the CDSNode class is implemented
   *
   * @todo this method does much more than just get the node, the name should reflect this
   *
   * @todo make sure this operation does not actually save the artifact to the database each
   *    time it is called
   */
  public function get_as_node() {

    // Create the artifact node and set moderation state to draft.
    $node = Node::create([
      'type' => 'artifact',
      'moderation_state' => 'draft',
    ]);

    // Fields with simple values (e.g., string).
    CDSArtifact::node_set_field($node,'title', $this->title);
    CDSArtifact::node_set_field($node,'field_description',$this->description);
    CDSArtifact::node_set_field($node,'field_identifier',$this->identifier);
    CDSArtifact::node_set_field($node,'field_version', $this->version);
    CDSArtifact::node_set_field($node,'field_experimental',(boolean) $this->experimental);
    CDSArtifact::node_set_field($node,'field_creation_date',$this->creation_date);
    CDSArtifact::node_set_field($node,'field_copyrights',$this->copyrights);
    CDSArtifact::node_set_field($node,'field_contributors',$this->contributors);
    CDSArtifact::node_set_field($node,'field_ip_attestation',(boolean) $this->ip_attestation);
    CDSArtifact::node_set_field($node,'field_payer', $this->payer);
    CDSArtifact::node_set_field($node,'field_code_system', $this->code_system);
    CDSArtifact::node_set_field($node,'field_erx_code', $this->electronic_prescribing_code);

    // Fields which reference at most one taxonomy term.
    // Status
    CDSArtifact::node_set_field($node,'field_status', $this->load_taxonomy_term_by_name(
      'status', $this->status));
    // Artifact type
    CDSArtifact::node_set_field($node,'field_artifact_type', $this->load_taxonomy_term_by_name(
      'artifact_type', $this->artifact_type));
    // License
    CDSArtifact::node_set_field($node,'field_license', $this->load_taxonomy_term_by_name(
      'license', $this->license));
    // Knowledge level
    CDSArtifact::node_set_field($node,'field_knowledge_level', $this->load_taxonomy_term_by_name(
      'knowledge_level', $this->knowledge_level));

    // Fields that potentially reference more than one taxonomy term.
    // Keywords
    $keyword_array = [];
    foreach((array) $this->keywords as $kw) {
      $keyword_array[] = $this->load_taxonomy_term_by_name('keywords', $kw);
    }
    CDSArtifact::node_set_field($node,'field_keywords', $keyword_array);
    // Topic tags derived from the National Library of Medicine 2019 Medical Subject Headings (MeSH) taxonomy
    $mesh_topic_array = [];
    foreach((array) $this->mesh_topics as $cd) {
      $mesh_topic_array[] = $this->load_taxonomy_term_by_name('mesh', $cd);
    }
    CDSArtifact::node_set_field($node,'field_mesh_topics', $mesh_topic_array);

    // Fields which reference one or more nodes.
    CDSArtifact::node_set_field($node,'field_related_artifacts', $this->load_nodes_by_name($this->related_artifacts));
    CDSArtifact::node_set_field($node,'field_steward', $this->load_nodes_by_name($this->steward));
    CDSArtifact::node_set_field($node,'field_publisher', $this->load_nodes_by_name($this->publisher));

    // All other fields are contained in paragraphs.
    CDSArtifact::node_set_field($node,'field_artifact_representation',$this->artifact_representation_paragraph());
    CDSArtifact::node_set_field($node,'field_implementation_details',$this->implementation_details_paragraph());
    CDSArtifact::node_set_field($node,'field_purpose_and_usage',$this->purpose_and_usage_paragraph());
    CDSArtifact::node_set_field($node,'field_supporting_evidence',$this->supporting_evidence_paragraph());
    CDSArtifact::node_set_field($node,'field_repository_information',$this->repository_information_paragraph());
    CDSArtifact::node_set_field($node,'field_testing_experience',$this->testing_experience_paragraph());

    $node->save();
    return $node;
  }

  /**
   *  Updates a Drupal 8 node with the data in this object
   *
   *  @todo this method is only used by PATCH, and is 95% identical to get_as_node().  The only
   *    portion that is different are the paragraphs.  This should be refactored for DRY.
   */
  public function update_node($node) {

    // Fields with simple values (e.g., string).
    CDSArtifact::node_set_field($node,'title', $this->title);
    CDSArtifact::node_set_field($node,'field_description',$this->description);
    CDSArtifact::node_set_field($node,'field_identifier',$this->identifier);
    CDSArtifact::node_set_field($node,'field_version', $this->version);
    CDSArtifact::node_set_field($node,'field_experimental',(boolean) $this->experimental);
    CDSArtifact::node_set_field($node,'field_creation_date',$this->creation_date);
    CDSArtifact::node_set_field($node,'field_copyrights',$this->copyrights);
    CDSArtifact::node_set_field($node,'field_contributors',$this->contributors);
    CDSArtifact::node_set_field($node,'field_ip_attestation',(boolean) $this->ip_attestation);
    CDSArtifact::node_set_field($node,'field_payer', $this->payer);
    CDSArtifact::node_set_field($node,'field_code_system', $this->code_system);
    CDSArtifact::node_set_field($node,'field_erx_code', $this->electronic_prescribing_code);

    // Fields which reference at most one taxonomy term.
    // Status
    CDSArtifact::node_set_field($node,'field_status', $this->load_taxonomy_term_by_name(
      'status', $this->status));
    // Artifact type
    CDSArtifact::node_set_field($node,'field_artifact_type', $this->load_taxonomy_term_by_name(
      'artifact_type', $this->artifact_type));
    // License
    CDSArtifact::node_set_field($node,'field_license', $this->load_taxonomy_term_by_name(
      'license', $this->license));
    // Knowledge level
    CDSArtifact::node_set_field($node,'field_knowledge_level', $this->load_taxonomy_term_by_name(
      'knowledge_level', $this->knowledge_level));

    // Fields that potentially reference more than one taxonomy term.
    // Keywords
    $keyword_array = [];
    foreach((array) $this->keywords as $kw) {
      $keyword_array[] = $this->load_taxonomy_term_by_name('keywords', $kw);
    }
    CDSArtifact::node_set_field($node,'field_keywords', $keyword_array);
    // Topic tags derived from the National Library of Medicine 2019 Medical Subject Headings (MeSH) taxonomy
    $mesh_topic_array = [];
    foreach((array) $this->mesh_topics as $cd) {
      $mesh_topic_array[] = $this->load_taxonomy_term_by_name('mesh', $cd);
    }
    CDSArtifact::node_set_field($node,'field_mesh_topics', $mesh_topic_array);

    // Fields which reference one or more nodes.
    CDSArtifact::node_set_field($node,'field_related_artifacts', $this->load_nodes_by_name($this->related_artifacts));
    CDSArtifact::node_set_field($node,'field_steward', $this->load_nodes_by_name($this->steward));
    CDSArtifact::node_set_field($node,'field_publisher', $this->load_nodes_by_name($this->publisher));

    // All other fields are contained in paragraphs.
    $paragraph_id = $node->get('field_artifact_representation')->target_id;
    CDSArtifact::node_set_field($node,'field_artifact_representation',$this->artifact_representation_paragraph($paragraph_id));
    $paragraph_id = $node->get('field_implementation_details')->target_id;
    CDSArtifact::node_set_field($node,'field_implementation_details',$this->implementation_details_paragraph($paragraph_id));
    $paragraph_id = $node->get('field_purpose_and_usage')->target_id;
    CDSArtifact::node_set_field($node,'field_purpose_and_usage',$this->purpose_and_usage_paragraph($paragraph_id));
    $paragraph_id = $node->get('field_supporting_evidence')->target_id;
    CDSArtifact::node_set_field($node,'field_supporting_evidence',$this->supporting_evidence_paragraph($paragraph_id));
    $paragraph_id = $node->get('field_repository_information')->target_id;
    CDSArtifact::node_set_field($node,'field_repository_information',$this->repository_information_paragraph($paragraph_id));
    $paragraph_id = $node->get('field_testing_experience')->target_id;
    CDSArtifact::node_set_field($node,'field_testing_experience',$this->testing_experience_paragraph($paragraph_id));

    $node->save();
    return $node;

  }


  // ----- Node utility methods ----- ----- ----- ----- ----- -----
  // @todo new class
  // @todo The intent of this section is to bring together all related methods in hope of moving them to a new class

  /**
   *  gets the value of a node's field
   *
   *  Note that if $field is a reference, it will use the reference's `name` property
   *      instead of its `value` property
   *  Note that the value could be
   *    null, a simple string, a boolean, or an array
   *
   *  @param mixed $node
   *    The node from where to get the data
   *
   *  @param string $field
   *    The name of the field in the node
   *
   *  @return the value of the $node's $field
   *
   *  @todo needs kernel testing
   */
  public static function node_get_value( $node, $field ) {
    if ( !$node || !$field ) {
      return null;
    }
    $nodeField = $node->get($field);
    // try first to get the value as if it is in the object
    $value = $nodeField->value;
    if ( !$value ) { // try to get the value(s) as if it is referenced as name
      if ( $nodeField->target_id ) {  // if the node is a simple string, integer, or boolean, it would not have target_id
        $count = count( $nodeField->referencedEntities() );
        if ( $count > 0 ) {
          $arr = [];
          foreach((array) $nodeField->referencedEntities() as $re) {
            $classname = get_class($re);
            if ($classname === 'Drupal\node\Entity\Node') {
              $arr[] = $re->get("title")->value;
            } elseif ($classname === 'Drupal\taxonomy\Entity\Term') {
              $arr[] = $re->getName();
            } elseif ($classname === 'Drupal\paragraphs\Entity\Paragraph') {
              $paragraph_properties = $re->toArray();
              foreach ($paragraph_properties as $ppk=>$ppv) {
                if (substr($ppk,0,6) === 'field_') {
                  if (isset($ppv[0]['value'])) {
                    $arr[$ppk] = $ppv[0]['value'];
                  }
                  elseif (isset($ppv[0]['target_id'])) {
                    $arr[$ppk] = $re->get($ppk)->referencedEntities();
                  }
                }
              }
            }
            else { }
          }
          $value = $arr;
        }
      }
    }
    return $value;
  }

  /**
   *  sets the field of a node
   *
   *  @param mixed $node
   *    The node to write data to
   *
   *  @param string $field
   *    The name of the field to be set in the node
   *
   *  @param mixed $value
   *    The value to be set
   */
  public static function node_set_field($node, $field, $value) {
    if (empty($value) && $value!=false) {
      return $node;
    }
    if ($node->hasField($field)) {
      $node->set($field, $value);
    } else {
      \Drupal::logger('cds_api')->error(
        t("Attempt to set non-existent node field @a",
        ['@a' => $field])
      );
    }
    return $node;
  }


  /**
   * Find and load the nodes named (in title)
   *
   * @param mixed $node_names
   *    An array of node names (titles)
   *
   * @return all matching nodes (i.e., nodes whose titles matched one of the titles in $node_names)
   */
  private function load_nodes_by_name($node_names) {
    if (empty($node_names)) {
      return null;
    }
    $loaded_nodes = [];
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    foreach((array) $node_names as $nn) {
      $query = \Drupal::entityQuery('node')->condition('title', $nn);
      $tmp1 = $query->execute();
      $tmp2 = array_reverse($tmp1);
      $nid = array_pop($tmp2);

      if (!is_null($nid)) {
        $loaded_nodes[] = $node_storage->load($nid);
      }

    }
    return $loaded_nodes;
  }


  // ----- Drupal taxonomy utility methods ----- ----- ----- ----- -----
  // @todo new class
  // @todo The intent of this section is to bring together all related methods in hope of moving them to a new class


  /**
   * Find and return the taxonomy term's node ID based on the name of the taxonomy term
   *
   * For example, for the Status taxonomy, one of the enumerated status values would be the term
   *
   * @param string $taxonomy
   *      The taxonomy class (e.g., `"status"`)
   *
   * @param string $term
   *      The enumerated value
   *
   * @return the node ID
   *
   * @todo This should really be called something like get_taxonomy_term_node_id_by_name()
   */
  public function load_taxonomy_term_by_name($taxonomy, $term) {
    $taxonomy_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($taxonomy);
    foreach ($taxonomy_terms as $taxonomy_term) {
      if ($taxonomy_term->name == $term) {
        return Term::load($taxonomy_term->tid);
      }
    }
  }


  /**
   * Find and return the taxonomy term based on the node ID
   *
   * For example, for the Status taxonomy, the node ID would return "Active"
   *
   * @param string $taxonomy
   *      The taxonomy class (e.g., `"status"`)
   *
   * @param mixed $id
   *      The node ID
   *
   * @return the taxonomy term
   *
   * @todo This should really be called something like get_taxonomy_term_by_node_id()
   */
  public function load_taxonomy_term_by_id($taxonomy, $id) {
    \Drupal::logger(__CLASS__."::".__FUNCTION__)->debug('$id:  '.$id);
    $taxonomy_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($taxonomy);
    foreach ($taxonomy_terms as $taxonomy_term) {
      \Drupal::logger(__CLASS__."::".__FUNCTION__)->debug('$tid:  '.$taxonomy_term->tid);
      if ($taxonomy_term->tid == $id) {
        return Term::load($id);
      }
    }
  }


  // ----- Paragraph utility methods ----- ----- ----- ----- -----
    // @todo new class
  // @todo The intent of this section is to bring together all related methods in hope of moving them to a new class


  /**
   * create and saves a paragram
   *
   * @param string $type
   *    The type of paragraph (e.g., 'artifact_representation')
   *
   * @param array $fields
   *    An array of fields (e.g., ['exclusions','inclusions', 'interventions_and_actions', 'triggers']);
   *
   * @return the saved paragraph
   */
  private function create_paragraph(string $type, array $fields) {
    if (!$this->validate_paragraph_type($type)) {
      throw new \Exception("Invalid paragraph type.");
    }
    $para = Paragraph::create(['type' => $type]);
    foreach($fields as $field) {
      $para->set('field_' . $field, $this->$field);
    }
    $para->save();
    return $para;
  }

  /**
   * updates and saves an existing paragraph
   *
   * @param string $id
   *    The node ID of the paragraph
   *
   * @param string $type
   *    The type of paragraph (e.g., 'artifact_representation')
   *
   * @param array $fields
   *    An array of fields (e.g., ['exclusions','inclusions', 'interventions_and_actions', 'triggers']);
   *
   * @return the saved paragraph
   */
  private function update_paragraph(string $id, string $type, array $fields) {
    if (!$this->validate_paragraph_type($type)) {
      throw new \Exception("Invalid paragraph type.");
    }
    $para = Paragraph::load($id);
    // TODO: Throw error if id does not exist
    foreach($fields as $field) {
      $para->set('field_' . $field, $this->$field);
    }
    $para->save();
    return $para;
  }


  /**
   * Verifies that the specified type is a paragraph
   *
   * @param string $type
   *    The type of paragraph (e.g., 'artifact_representation')
   *
   * @return a boolean to indicate if the specified type is a paragraph
   */
  private function validate_paragraph_type(string $type) {
    if (in_array($type, $this->valid_paragraph_types)) { return true; }
    else { return false; }
  }


  /**
   * Specialized version of create_paragraph and update_paragraph, which
   * creates or updates and saves the Artifact Representation paragraph
   *
   * @param string $id
   *    The node ID of the paragraph; if not specified, then assumes creating a new paragraph
   *
   * @return the saved paragraph
   */
  private function artifact_representation_paragraph($paragraph_id=NULL) {
    $type = 'artifact_representation';
    $fields = ['exclusions',
               'inclusions',
               'interventions_and_actions',
               'triggers'];
    if ($paragraph_id === NULL) {
      return $this->create_paragraph($type, $fields);
    } else {
      return $this->update_paragraph($paragraph_id, $type, $fields);
    }
  }

  /**
   * Specialized version of create_paragraph and update_paragraph, which
   * creates or updates and saves the Implementation Details paragraph
   *
   * @param string $id
   *    The node ID of the paragraph; if not specified, then assumes creating a new paragraph
   *
   * @return the saved paragraph
   */
  private function implementation_details_paragraph($paragraph_id=NULL) {
    $type = 'implementation_details';
    $fields = ['engineering_details'];
    if ($paragraph_id === NULL) {
      return $this->create_paragraph($type, $fields);
    } else {
      return $this->update_paragraph($paragraph_id, $type, $fields);
    }
  }


  /**
   * Specialized version of create_paragraph and update_paragraph, which
   * creates or updates and saves the Purpose and Usage paragraph
   *
   * @param string $id
   *    The node ID of the paragraph; if not specified, then assumes creating a new paragraph
   *
   * @return the saved paragraph
   */
  private function purpose_and_usage_paragraph($paragraph_id=NULL) {
    $type = 'purpose_and_usage';
    $fields = ['cautions',
               'intended_population',
               'purpose',
               'usage'];
    if ($paragraph_id === NULL) {
      return $this->create_paragraph($type, $fields);
    } else {
      return $this->update_paragraph($paragraph_id, $type, $fields);
    }
  }

  /**
   * Specialized version of create_paragraph and update_paragraph, which
   * creates or updates and saves the Supporting Evidence paragraph
   *
   * @param string $id
   *    The node ID of the paragraph; if not specified, then assumes creating a new paragraph
   *
   * @return the saved paragraph
   */
  private function supporting_evidence_paragraph($paragraph_id=NULL) {
    $type = 'supporting_evidence';
    $fields = ['source_description',
               'references',
               'artifact_decision_notes'];
    if ($paragraph_id === NULL) {
      $se_para = $this->create_paragraph($type, $fields);
    } else {
      $se_para = $this->update_paragraph($paragraph_id, $type, $fields);
    }
    // Add source reference
    $se_para->set('field_source', $this->load_nodes_by_name($this->source));
    // Add recommendation statements
    $rec_state_paras = [];
    if (!is_null($this->recommendation_statement)) {
      foreach ($this->recommendation_statement as $rs) {
        $rec_state_paras[] = $this->recommendation_statement_paragraph($rs);
      }
    }
    $se_para->set('field_recommendation_statement', $rec_state_paras);
    $se_para->save();
    return $se_para;
  }


  /**
   * Specialized version of create_paragraph and update_paragraph, which
   * creates or updates and saves the Recommendation Statement paragraph
   *
   * @param string $id
   *    The node ID of the paragraph; if not specified, then assumes creating a new paragraph
   *
   * @return the saved paragraph
   */
  private function recommendation_statement_paragraph($rs) {
    $type = 'recommendation_statement';
    $fields = ['recommendation',
               'strength_of_recommendation',
               'quality_of_evidence',
               'decision_notes'];

    $para = Paragraph::create(['type' => $type]);
    foreach($fields as $field) {
      $para->set('field_' . $field, $rs->$field);
    }
    $para->save();
    return $para;
  }


  /**
   * Specialized version of create_paragraph and update_paragraph, which
   * creates or updates and saves the Repository Information paragraph
   *
   * @param string $id
   *    The node ID of the paragraph; if not specified, then assumes creating a new paragraph
   *
   * @return the saved paragraph
   */
  private function repository_information_paragraph($paragraph_id=NULL) {
    $type = 'repository_information';
    $fields = ['approval_date',
               'expiration_date',
               'last_review_date',
               'publication_date'];
    if ($paragraph_id === NULL) {
      return $this->create_paragraph($type, $fields);
    } else {
      return $this->update_paragraph($paragraph_id, $type, $fields);
    }
  }


  /**
   * Specialized version of create_paragraph and update_paragraph, which
   * creates or updates and saves the Testing Experience paragraph
   *
   * @param string $id
   *    The node ID of the paragraph; if not specified, then assumes creating a new paragraph
   *
   * @return the saved paragraph
   */
  private function testing_experience_paragraph($paragraph_id=NULL) {
    $type = 'testing_experience';
    $fields = ['pilot_experience'];
    if ($paragraph_id === NULL) {
      return $this->create_paragraph($type, $fields);
    } else {
      return $this->update_paragraph($paragraph_id, $type, $fields);
    }
  }


}