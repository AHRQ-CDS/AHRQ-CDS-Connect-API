<?php

namespace Drupal\Tests\cds_api\Unit;

use Drupal\Tests\cds_api\Unit\CDSUtils;

use Drupal\cds_api\Plugin\rest\resource\CDSArtifact;
use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSNonconformantJsonException;
use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * CDSArtifactTest
 * @group restUnit
 */
class CDSArtifactTest extends TestCase
{
    /**
     * Modules to enable.
     *
     * @note  not sure this is needed, but was referenced in one of the blogs
     *          https://www.mediacurrent.com/blog/writing-simple-phpunit-tests-your-d8-module/
     * @var array
     */
    public static $modules = ['cds_api'];


    // ----- Internal object tests ---------------------------------------------


    /** @test
     *      Full test of schema to make sure that if changes were inadvertantly made, it would be noticed
    */
    public function test_schema()
    {
        $schema = CDSArtifact::get_schema();
        $testSchema = CDSUtils::read_json("persisted_cds_schema.json" );
        $this->assertJsonStringEqualsJsonString( json_encode( $testSchema ), json_encode( $schema ) );
    }


    /** @test
     *      Very simple test to verify a few things when using the method; full test in test_schema
     *      also a good idea to access the schema in a different way than full object equals as in the full test
    */
    public function test_get_schema()
    {
        // fwrite(STDOUT, __METHOD__ . "\n");
        $schema = CDSArtifact::get_schema();
        $this->assertEquals( $schema->title, "CDS Connect Schema v1 (draft)");
        $this->assertEquals( $schema->properties->title->type, "string");
    }


    /** @test */
    public function test_constructor()
    {
        $json = CDSUtils::read_json( "request_minimal.json" );
        $artifact = new CDSArtifact();
        $artifact->load_json($json);
        $this->assertEquals( $artifact->get_title(), "title (test 0 + 1 )" );
    }


    /** @test */
    public function test_get_title()
    {
        $artifact = CDSUtils::setup_artifact( "request_minimal.json" );
        $this->assertEquals( $artifact->get_title(), "title (test 0 + 1 )" );
    }


    /** @test */
    public function test_get_value()
    {
        $artifact = CDSUtils::setup_artifact( "request_minimal.json" );
        $this->assertEquals( $artifact->get_value( "title" ), "title (test 0 + 1 )" );
        $this->assertEquals( $artifact->get_value( "version" ), "1.2.3" );
        // $this->assertEquals( $artifact->get_value( "status" ), 208 );   // will be tested in Postman
        // $this->assertEquals( $artifact->get_value( "artifact_type" ), 1 );  // will be tested in Postman

        $this->assertEquals( $artifact->get_value( "description" ), null ); // wasn't specified
        $this->assertEquals( $artifact->get_value( "non_existent_key" ), null );
        $this->assertEquals( $artifact->get_value( "😀" ), null );
    }


    /** @test
     * @dataProvider _status_provider
     */
    public function test_status_tid( $status, $tid )
    {
        $this->assertEquals( CDSUtils::get_status_tid( $status ), $tid );
    }


    public function _status_provider()
    {
        return [
            [ "_default_", 206 ],
            [ "Active", 206 ],
            [ "Draft", 205 ],
            [ "Retired", 207 ],
            [ "Unknown", 208 ]
        ];
    }


    /** @test
     * @dataProvider _artifact_type_provider
     */
    public function test_artifact_type_tid( $at, $tid )
    {
        $this->assertEquals( CDSUtils::get_artifact_type_tid( $at ), $tid );
    }


    public function _artifact_type_provider()
    {
        return [
            [ "_default_", 7 ],
            [ "Report", 9 ],
            [ "Reference Information", 7 ],
            [ "Reminder", 8 ],
            [ "Warning", 12 ],
            [ "Alert", 1 ],
            [ "Smart Documentation Form", 11 ],
            [ "Order Set", 5 ],
            [ "Event-Condition-Action (ECA) rule", 3 ],
            [ "InfoButton", 4 ],
            [ "Parameter Guidance", 6 ],
            [ "Risk Assessment", 10 ],
            [ "Data Summary", 12 ],
        ];
    }


    // ----- Request-oriented tests (basic) ---------------------------------------------


    /** @test */
    public function test_request_empty_no_default()
    {
        CDSUtils::assert_json_file_to_schema(
            "request_empty.json",
            [],
            false );
    }


    /** @test */
    public function test_request_empty_with_default()
    {
        $artifact = CDSUtils::setup_artifact( "request_empty.json" );
        $this->assertContains( "CDS Artifact uploaded on", $artifact->get_title() );
        $this->assertEquals( $artifact->get_value('version'), "0.1" );
        $this->assertEquals( $artifact->get_value('status'), "Active" );
        $this->assertEquals( $artifact->get_value('artifact_type'), "Reference Information" );
    }


    /** @test */
    public function test_request_simple()
    {
        // test against schema
        CDSUtils::assert_json_file_to_schema( "request_simple.json" );

        // test constructor
        $artifact = CDSUtils::setup_artifact( "request_minimal.json" );
        $this->assertEquals( $artifact->get_title(), "title (test 0 + 1 )" );
        $this->assertEquals( $artifact->get_value('version'), "1.2.3" );
        // $this->assertEquals( $artifact->get_value('status'), 208 ); // will be tested in Postman
        // $this->assertEquals( $artifact->get_value('artifact_type'), 12 );   // will be tested in Postman
    }


    /** @test */
    public function test_JSON_Schema_title_only()
    {
        CDSUtils::assert_json_file_to_schema(
            "request_title_only.json" );

        // test constructor
        $artifact = CDSUtils::setup_artifact( "request_title_only.json" );
        $this->assertEquals( $artifact->get_title(), "title (test 0 + 1 )" );
        $this->assertEquals( $artifact->get_value('version'), "0.1" );
        // $this->assertEquals( $artifact->get_value('status'), 206 ); // will be tested in Postman
        // $this->assertEquals( $artifact->get_value('artifact_type'), 7 );    // will be tested in Postman
    }


    // ----- Request-oriented validation and sanitation tests ---------------------------------------------


    /** @test */
    public function test_utf8_normal_request()
    {
        $artifact = CDSUtils::setup_artifact( "request_minimal.json" );
        $this->assertEquals( $artifact->get_title(), "title (test 0 + 1 )" );
        $this->assertEquals( $artifact->get_value( "title" ), "title (test 0 + 1 )" );
        $this->assertEquals( $artifact->get_value( "version" ), "1.2.3" );
        // $this->assertEquals( $artifact->get_value( "status" ), 208 );    // will be tested in Postman
        // $this->assertEquals( $artifact->get_value( "artifact_type" ), 1 );  // will be tested in Postman
    }


    /** @test */
    public function test_normal_request1()
    {
        $artifact = CDSUtils::setup_artifact( "request_rich_text.json" );
        $this->assertEquals( $artifact->get_title(), "<em>française</em>😀中文" );
        $this->assertEquals( $artifact->get_value( "description" ), 'Please refer to <a href="http://example.com">example site</a>' );
        $this->assertEquals( $artifact->get_value( "copyrights" ), "i++;" );
        $this->assertEquals( $artifact->get_value( "contributors" ), "a++;" );
    }

    /** @test */
    public function test_complete_request() {
        $artifact = CDSUtils::setup_artifact( "request_complete_no_files.json" );
        $this->assertEquals( $artifact->get_title(), "Title" );
        $this->assertEquals( $artifact->get_value( "description" ), "Description" );
        $this->assertEquals( $artifact->get_value( "identifier" ), "1" );
        $this->assertEquals( $artifact->get_value( "version" ), "1" );
        $this->assertEquals( $artifact->get_value( "status" ), "Draft" );
        $this->assertEquals( $artifact->get_value( "experimental" ), true );
        $this->assertEquals( $artifact->get_value( "artifact_type" ), "Alert" );
        $this->assertEquals( $artifact->get_value( "creation_date" ), "2018-11-01T01:19:50Z" );
        $this->assertEquals( $artifact->get_value( "license" ), "Apache" );
        $this->assertEquals( $artifact->get_value( "copyrights" ), "Copyright" );
        $this->assertArraySubset( $artifact->get_value( "keywords" ), ["key1", "key2"] );
        $this->assertArraySubset( $artifact->get_value( "steward" ), ["1"] );
        $this->assertArraySubset( $artifact->get_value( "publisher" ), ["1"] );
        $this->assertEquals( $artifact->get_value( "contributors" ), "Contributors" );
        $this->assertEquals( $artifact->get_value( "ip_attestation" ), true );
        $this->assertArraySubset( $artifact->get_value( "clinical_domains" ), ["cd1", "cd2"] );
        $this->assertEquals( $artifact->get_value( "knowledge_level" ), "1-Narrative" );
        $this->assertArraySubset( $artifact->get_value( "related_artifacts" ), ["2"] );
        $this->assertEquals( $artifact->get_value( "triggers" ), "Triggers" );
        $this->assertEquals( $artifact->get_value( "inclusions" ), "Inclusions" );
        $this->assertEquals( $artifact->get_value( "exclusions" ), "Exclusions" );
        $this->assertEquals( $artifact->get_value( "interventions_and_actions" ), "Interventions and actions" );
        $this->assertArraySubset( $artifact->get_value( "logic_files" ), [""] );
        $this->assertEquals( $artifact->get_value( "engineering_details" ), "Engineering details" );
        $this->assertArraySubset( $artifact->get_value( "technical_files" ), [""] );
        $this->assertArraySubset( $artifact->get_value( "miscellaneous_files" ), [""] );
        $this->assertEquals( $artifact->get_value( "purpose" ), "Purpose" );
        $this->assertEquals( $artifact->get_value( "intended_population" ), "Intended population" );
        $this->assertEquals( $artifact->get_value( "usage" ), "Usage" );
        $this->assertEquals( $artifact->get_value( "cautions" ), "Cautions" );
        $this->assertArraySubset( $artifact->get_value( "test_patients" ), [""] );
        $this->assertEquals( $artifact->get_value( "source_description" ), "Source description" );
        $this->assertEquals( $artifact->get_value( "source" ), "3" );
        $this->assertEquals( $artifact->get_value( "references" ), "References" );
        $this->assertEquals( $artifact->get_value( "artifact_decision_notes" ), "Artifact decision notes" );
        $rec_states = $artifact->get_value( "recommendation_statement" )[0];
        $this->assertEquals( $rec_states->decision_notes, "Decision notes" );
        $this->assertEquals( $rec_states->quality_of_evidence, "Quality of evidence" );
        $this->assertEquals( $rec_states->recommendation, "Recommendation" );
        $this->assertEquals( $rec_states->strength_of_recommendation, "Strength of recommendation" );
        $rec_states = $artifact->get_value( "recommendation_statement" )[1];
        $this->assertEquals( $rec_states->decision_notes, "Decision notes 2" );
        $this->assertEquals( $rec_states->quality_of_evidence, "Quality of evidence 2" );
        $this->assertEquals( $rec_states->recommendation, "Recommendation 2" );
        $this->assertEquals( $rec_states->strength_of_recommendation, "Strength of recommendation 2" );
        $this->assertEquals( $artifact->get_value( "approval_date" ), "2018-11-01T01:19:50Z" );
        $this->assertEquals( $artifact->get_value( "expiration_date" ), "2018-11-01T01:19:50Z" );
        $this->assertEquals( $artifact->get_value( "last_review_date" ), "2018-11-01T01:19:50Z" );
        $this->assertEquals( $artifact->get_value( "publication_date" ), "2018-11-01T01:19:50Z" );
        $this->assertEquals( $artifact->get_value( "preview_image" ), "" );
        $this->assertEquals( $artifact->get_value( "pilot_experience" ), "Pilot experience" );

    }

}
