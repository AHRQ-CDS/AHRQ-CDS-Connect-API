<?php

namespace Drupal\Tests\cds_api\Unit;

use PHPUnit\Framework\Assert;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Drupal\cds_api\Plugin\rest\resource\CDSArtifact;


/**
 * CDSUtils
 *  A set of utilities that are useful when testing CDS code
 */
class CDSUtils
{

    /** constant to help us use local test input/output files */
    private static $_path_to_module_test_base = null;   // lazy init


    /** the (decoded) cds schema */
    private static $schema = null;   // lazy init


    // ----- Environment methods ---------------------------------------------


    public static function getTestBasePath()
    {
    	if ( CDSUtils::$_path_to_module_test_base == null ) {
            CDSUtils::$_path_to_module_test_base = __DIR__;
        }
        return CDSUtils::$_path_to_module_test_base;
    }


    // ----- Schema methods ---------------------------------------------


    public static function getSchema()
    {
        if ( CDSUtils::$schema == null ) {
            CDSUtils::$schema = CDSArtifact::get_schema();
        }
        return CDSUtils::$schema;
    }



    /** returns the status TID given the status string */
    public static function get_status_tid( $status ): int
    {
        //@todo hk a better implmentation may be to use the taxonomy nodes
        static $status_to_tid = [
            "_default_" => 206,
            "Active" => 206,
            "Draft" => 205,
            "Retired" => 207,
            "Unknown" => 208,
        ];
        return ( array_key_exists( $status, $status_to_tid ) ) ? $status_to_tid[$status] : $status_to_tid["_default_"];
    }


    /** returns the artifact type TID given the artifact type string */
    public static function get_artifact_type_tid( $at ): int
    {
        //@todo hk a better implmentation may be to use the taxonomy nodes
        static $at_to_tid = [
            "_default_" => 7,
            "Report" => 9,
            "Reference Information" => 7,
            "Reminder" => 8,
            "Warning" => 12,
            "Alert" => 1,
            "Smart Documentation Form" => 11,
            "Order Set" => 5,
            "Event-Condition-Action (ECA) rule" => 3,
            "InfoButton" => 4,
            "Parameter Guidance" => 6,
            "Risk Assessment" => 10,
            "Data Summary" => 12,
        ];
        return ( array_key_exists( $at, $at_to_tid ) ) ? $at_to_tid[$at] : $at_to_tid["_default_"];
    }


    // ----- Utility JSON file methods ---------------------------------------------


    /** utility method to return the path to a fixture file
     * @param $filename the filename + extension in the fixtures directory
     */
    public static function get_fixture_file( $filename )
    {
        return dirname(__FILE__, 3) . "/fixtures/" . $filename;
    }


    /** utility method to make a new JSON object from a json file in the fixtures subdirectory
     *  @param $filename - the filename from the fixtures directory (e.g., request_minimal.json)
     */
    public static function read_json( $filename ) {
        $jsonstr = file_get_contents( CDSUtils::get_fixture_file( $filename ) );
        return json_decode( $jsonstr );
    }


    /** utility method to make a new CDSArtifact from a test json file
     *  @param $filename - the filename from the fixtures directory (e.g., request_minimal.json)
     */
    public static function setup_artifact( $filename ) {
        $json = CDSUtils::read_json( $filename );
        $artifact = new CDSArtifact();
        $artifact->load_json($json);
        return $artifact;
    }


    // ----- Utility JSON-Schema methods ---------------------------------------------


    /** utility to validate a json object against the CDS schema,
     * @param the JSON object
     *  @return the Validator object used to valiate so that any errors can be processed/noted
    */
    public static function validate_json( $json, $applyDefaults=true ): Validator
    {
        // return CDSArtifact::validate_json( $json, $applyDefaults );

        $schema = CDSUtils::getSchema();

        $validator = new Validator;
        $constraints = $applyDefaults ? Constraint::CHECK_MODE_APPLY_DEFAULTS : Constraint::CHECK_MODE_NONE;
        $validator->validate( $json, $schema, $constraints );

        return $validator;
    }


    /** utility to read in a json file, and run validation on it against the CDS schema,
     * @filename the name of the json file
     *  @return the Validator object used to valiate so that any errors can be processed/noted
    */
    public static function validate_json_file( $filename, $applyDefaults=true ): Validator
    {
        $artifact = CDSUtils::read_json( $filename );
        return CDSUtils::validate_json( $artifact, $applyDefaults );
    }


    /** utility to simplify testing json files and the CDS schema
     * @param $json the json object
     * @param $expectedErrors an optional array of expected errors
     *          which is checked if the schema validation fails
     * @note Note that if the json is valid, and expectedErrors is specified,
     *          and there are no errors, it will return false
    */
    public static function assert_json_to_schema( $json, $expectedErrors=[], $applyDefaults=true )
    {
        $validator = CDSUtils::validate_json( $json, $applyDefaults, $applyDefaults );
        $isValid = $validator->isValid();
        $validatorErrors = CDSUtils::get_schema_validation_errors_as_array( $validator );
        Assert::assertEquals( $expectedErrors, $validatorErrors );
        $retval = ( $isValid ) ? ( $expectedErrors == $validatorErrors ): false;
    }


    /** utility to simplify testing json files and the CDS schema
     * @param $filename filename of test json file
     * @param $expectedErrors an optional array of expected errors (only
     *              checked if the schema validation fails)
    */
    public static function assert_json_file_to_schema( $filename, $expectedErrors=[], $applyDefaults=true )
    {
        $artifact = CDSUtils::read_json( $filename );
        return CDSUtils::assert_json_to_schema( $artifact, $expectedErrors, $applyDefaults );
    }


    /** @returns the validation error object as an array of errors
     *      of the form [key] => message
     */
    public static function get_schema_validation_errors_as_array( $validator )
    {
        $retval = [];
        foreach ($validator->getErrors() as $error) {
            $retval[$error['property']] = $error['message'];
        }
        return $retval;
    }


    /** @returns all the validation errors in the validator error object as a string
     *      useful for printing out the errors in phpunit assert statements
    */
    public static function get_schema_validation_errors_as_string( $validator )
    {
        $retval = "";
        foreach ($validator->getErrors() as $error) {
            $retval = $retval . sprintf("[%s] %s\n", $error['property'], $error['message']);
        }
        return $retval;
    }


    // ----- Test for the utility methods -----------------------------------


    /** @test
     *      Tests to verify we're getting the CDS Connect Schema as persisted when
     *          the tests were last updated
     *      Note that this needs to use CDSArtifact::get_schema() even though it is already done
     *          in setUpBeforeClass() so that if there is a failure, it would be noted "officially"
    */
    public function test_schema()
    {
        $this->assertJsonStringEqualsJsonFile(
                    dirname(__FILE__, 3) . "/fixtures/persisted_cds_schema.json",
                    json_encode( CDSUtils::getSchema() ) );
    }

}
