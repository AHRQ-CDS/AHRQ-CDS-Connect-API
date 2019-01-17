<?php

namespace Drupal\Tests\cds_api\Unit;

use Drupal\Tests\cds_api\Unit\CDSUtils;
use Drupal\cds_api\Plugin\rest\resource\CDSResource;
use Drupal\cds_api\Plugin\rest\resource\CDSSchema;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;


/**
 * JSONSchemaTest
 *      These are a set of tests to make sure that as JSONSchema is updated
 *      its expected behavior in CDS code continues to function as expected
 * @group restUnit
 */
class JSONSchemaTest extends TestCase
{

    // ----- Utility method tests ---------------------------------------------

    /** @test
     * @dataProvider _test_type_provider
     */
    public function test_json_schema( $s, $d, $expected )
    {
        $schema = json_decode( $s, true );
        $data = json_decode( $d, true );
        $validator = new Validator();
        // Note:  this at first looked like a good idea, but it can cause unexpected as well as expected
        //          coersions (e.g, real arrays into strings) that can cause other problems; therefore we're
        //          forcing type in input
        // $validator->validate($data, $schema, Constraint::CHECK_MODE_COERCE_TYPES );
        $validator->validate( $data, $schema );
        if ( $expected ) {
            $this->assertTrue(
                $validator->isValid(),
                CDSSchema::get_schema_validation_errors_as_string($validator) );
        }
        else {
            $this->assertFalse(
                $validator->isValid(),
                CDSSchema::get_schema_validation_errors_as_string($validator) );
        }
    }


    public function _test_type_provider()
    {
        // note that in the list of schemas below, a $s_nXXX denotes a type that can be nullable
        $s_arrayStrings = '{"properties":{"p1":{"type":"array","items":[{"type":"string"}]}}}';
        $s_narrayStrings = '{"properties":{"p1":{"type":["array","null"],"items":[{"type":"string"}]}}}';
        $s_arrayInts =    '{"properties":{"p1":{"type":"array","items":[{"type":"integer"}]}}}';
        $s_integer =      '{"properties":{"p1":{"type":"integer"}}}';
        $s_ninteger =     '{"properties":{"p1":{"type":["integer","null"]}}}';
        $s_boolean =      '{"properties":{"p1":{"type":"boolean"}}}';
        $s_nboolean =     '{"properties":{"p1":{"type":["boolean","null"]}}}';
        $s_string =       '{"properties":{"p1":{"type":"string"}}}';
        $s_nstring =      '{"properties":{"p1":{"type":["string","null"]}}}';
        $s_datetime =     '{"properties":{"p1":{"type":"string", "format": "date-time"}}}';
        $s_ndatetime =    '{"properties":{"p1":{"type":["string","null"], "format": "date-time"}}}';
        $s_date =         '{"properties":{"p1":{"type":"string", "format": "date"}}}';
        $s_time =         '{"properties":{"p1":{"type":"string", "format": "time"}}}';
        // $s_dt_multi2 =     '{"properties":{"p1":{"type":"string", "format": [ "date", "time" ]}}}';
        $s_email =        '{"properties":{"p1":{"type":"string","format":"email"}}}';
        $s_object =       '{"properties":{"p1":{"type":"object","properties":{"a":{"type":"string"},"b":{"type":"string"}}}}}';
        $s_nobject =      '{"properties":{"p1":{"type":["object","null"],"properties":{"str":{"type":"string"}}}}}';

        return [
            // $schema, $data, $expected

            // safe text
            [ $s_arrayStrings,   '{"p1":["1","2","3"]}',               true ],
            [ $s_arrayStrings,   '{"p1":[1,2,3]}',                     false ],
            [ $s_arrayStrings,   '{"p1":[]}',                          true ],
            [ $s_arrayStrings,   '{"p1":null}',                        false ], // null is not a valid array, unfortunately
            [ $s_narrayStrings,  '{"p1":null}',                        true ],
            [ $s_arrayInts,      '{"p1":[1,2,3]}',                     true ],
            [ $s_arrayInts,      '{"p1":["1","2","3"]}',               false ],
            [ $s_integer,        '{"p1":42}',                          true ],
            [ $s_integer,        '{"p1":40 + 2}',                      true ],  // not sure why this is valid
            [ $s_integer,        '{"p1":true}',                        false ],
            [ $s_integer,        '"42"',                               true ],  // not sure why this is valid
            [ $s_ninteger,       '{"p1":null}',                        true ],
            [ $s_boolean,        '{"p1":true}',                        true ],
            [ $s_boolean,        '{"p1":false}',                       true ],
            [ $s_boolean,        '{"p1":null}',                        false ],
            [ $s_nboolean,       '{"p1":null}',                        true ],
            [ $s_boolean,        '{"p1":42}',                          false ],
            [ $s_boolean,        '{"p1":"false"}',                     false ],
            [ $s_string,         '{"p1":"abc"}',                       true ],
            [ $s_string,         '{"p1":true}',                        false ],
            [ $s_string,         '{"p1":42}',                          false ],
            [ $s_string,         '{"p1":1.2}',                         false ],
            [ $s_string,         '{"p1":null}',                        false ],
            [ $s_nstring,        '{"p1":null}',                        true ],
            [ $s_datetime,       '{"p1":"2018-10-31"}',                false ],    // legal ISO 8601, but need to use format "date" instea of "date-time"
            [ $s_datetime,       '{"p1":"2018-10-31T23:19:50Z"}',      true ],
            [ $s_datetime,       '{"p1":"2018-10-31T23:19:50+00:00"}', true ],
            [ $s_datetime,       '{"p1":"20181031T231950Z"}',          false ],   // legal ISO 8601, but not for JSON Schema
            [ $s_datetime,       '{"p1":null}',                        false ],
            [ $s_ndatetime,      '{"p1":null}',                        true ],
            [ $s_date,           '{"p1":"2018-10-31"}',                true ],
            [ $s_time,           '{"p1":"2:19:50"}',                   false ],
            [ $s_time,           '{"p1":"02:19:50"}',                  true ],
            // [ $s_dt_multi2,       '{"p1":"true"}',                 true ],
            // [ $s_dt_multi2,       '{"p1":"2018-10-31"}',           true ],
            // [ $s_dt_multi2,       '{"p1":"2018"}',                 true ],
            [ $s_email,          '{"p1":"abc@example.com"}',           true ],
            [ $s_email,          '{"p1":"asdf"}',                      false ],
            [ $s_email,          '{"p1":"example.com"}',               false ],
            // [ $s_object,         '{"p1":{"str1":"1"}}',              true ], // @todo we do not have tests on type "object", because it keeps thinking it is an array
            [ $s_object,         '{"p1":null}',                        false ],
            [ $s_nobject,        '{"p1":null}',                        true ],
        ];
    }


    /** @test */
    public function test_request_empty_no_defaults()
    {
        CDSUtils::assert_json_file_to_schema(
            "request_empty.json" );
    }


    /** @test */
    public function test_request_empty_with_defaults()
    {
        CDSUtils::assert_json_file_to_schema(
            "request_empty.json" );
    }


    /** @test */
    public function test_request_representative_types()
    {
        CDSUtils::assert_json_file_to_schema(
            "request_different_types.json" );
    }


    /** @test */
    public function test_request_bad_types()
    {
        CDSUtils::assert_json_file_to_schema(
            "request_wrong_types.json",
            [   'title' => 'Integer value found, but a string is required',
                'version' => 'Double value found, but a string is required',
                'status' => 'Does not have a value in the enumeration ["Active","Retired","Draft","Unknown"]',
                'experimental' => 'String value found, but a boolean is required',
                'creation_and_usage.keywords' => 'Object value found, but an array is required',
                'testing_experience' => 'Array value found, but an object is required',
                'artifact_type' => 'Does not have a value in the enumeration ["Alert","Data Summary","Event-Condition-Action (ECA) rule","InfoButton","Order Set","Parameter Guidance","Reference Information","Reminder","Report","Risk Assessment","Smart Documentation Form","Warning"]',
                'creation_date' => 'Invalid date "2018-01", expected format YYYY-MM-DD'
            ] );
    }


    /** @test
     *  @note this test is a quirk of the library: it does not flag keys that are not
     *      not defined in the schema
     */
    public function test_request_unspecified_key()
    {
        $json = CDSUtils::read_json( "request_minimal.json" );

        $json->unspecifiedKey = "Does not notify when unspecified key is used";
        CDSUtils::assert_json_to_schema( $json );
    }


    /** @test */
    public function test_request_wrong_type_array()
    {
        $json = CDSUtils::read_json( "request_different_types.json" );
        CDSUtils::assert_json_to_schema( $json );

        $json->creation_and_usage = (object) array( "keywords" => "keywords" );
        CDSUtils::assert_json_to_schema( $json,
            [ "creation_and_usage.keywords" => 'String value found, but an array is required' ] );

        // @todo: don't know how to test an array programmatically here,
        //  but it is tested in the request_different_types.json file
        // print_r( $json );
        // $arr = array( "1", "2", "3" );
        // $json->creation_and_usage = (object) array( "keywords" => $arr );
        // CDSUtils::assert_json_to_schema( $json,
        //     [ "creation_and_usage.keywords" => 'String value found, but an array is required' ] );
    }


    /** @test */
    public function test_request_wrong_type_boolean()
    {
        $json = CDSUtils::read_json( "request_minimal.json" );

        $json->experimental = 35;
        CDSUtils::assert_json_to_schema( $json,
            [ "experimental" => 'Integer value found, but a boolean is required' ] );

        $json->experimental = "true";
        CDSUtils::assert_json_to_schema( $json,
            [ "experimental" => 'String value found, but a boolean is required' ] );

        $json->experimental = true;
        CDSUtils::assert_json_to_schema( $json );

        $json->experimental = $this;
        CDSUtils::assert_json_to_schema( $json,
            [ 'experimental' => 'Object value found, but a boolean is required' ] );
    }


    /** @test */
    public function test_request_wrong_type_object()
    {
        $json = CDSUtils::read_json( "request_minimal.json" );

        $json->creation_and_usage = 35;
        CDSUtils::assert_json_to_schema( $json,
            [ "creation_and_usage" => 'Integer value found, but an object is required' ] );

        $json->creation_and_usage = (object) array( "license" => "text" );
        CDSUtils::assert_json_to_schema( $json );
    }


    /** @test */
    public function test_request_wrong_type_string()
    {
        $json = CDSUtils::read_json( "request_minimal.json" );

        $json->title = 35;
        CDSUtils::assert_json_to_schema( $json,
            [ 'title' => 'Integer value found, but a string is required' ],
            true );

        $json->title = "35";
        CDSUtils::assert_json_to_schema( $json );

    }


    /** @test */
    public function test_request_wrong_type_enum()
    {
        $json = CDSUtils::read_json( "request_wrong_enum.json" );
        CDSUtils::assert_json_to_schema( $json,
            [ 'status' => 'Does not have a value in the enumeration ["Active","Retired","Draft","Unknown"]',
              'artifact_type' => 'Does not have a value in the enumeration ["Alert","Data Summary","Event-Condition-Action (ECA) rule","InfoButton","Order Set","Parameter Guidance","Reference Information","Reminder","Report","Risk Assessment","Smart Documentation Form","Warning"]' ],
            true );

    }


    /** @test */
    public function test_patch_clear_string()
    {
        // ok ways to clear a string
        $json = CDSUtils::read_json( "request_different_types.json" );
        $json->description = "";
        CDSUtils::assert_json_to_schema( $json, [], true );

        $json->description = null;
        CDSUtils::assert_json_to_schema( $json, [], true );

        // this fails because of null, but really should also fail because it's required
        $json->version = null;
        CDSUtils::assert_json_to_schema( $json,
            [ 'version' => 'NULL value found, but a string is required' ],
            true );
    }



}
