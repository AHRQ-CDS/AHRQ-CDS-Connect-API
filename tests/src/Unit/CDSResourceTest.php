<?php

namespace Drupal\Tests\cds_api\Unit;

use Drupal\Tests\cds_api\Unit\CDSUtils;

use Drupal\Component\Utility\Xss;
use PHPUnit\Framework\TestCase;

use Drupal\cds_api\Plugin\rest\resource\CDSArtifCDact;
use Drupal\cds_api\Plugin\rest\resource\CDSResource;

/**
 * CDCSResourceTest
 * @group restUnit
 */
class CDCSResourceTest extends TestCase
{
    /**
     * Modules to enable.
     *
     * @note not sure this is needed, but was referenced in one of the blogs
     *          https://www.mediacurrent.com/blog/writing-simple-phpunit-tests-your-d8-module/
     * @var array
     */
    public static $modules = ['cds_api'];

    // ----- Utility methods ---------------------------------------------


    // /** utility test class to make a new CDSArtifact from a test json file
    //  *  @param $filename - the filename from the fixtures directory (e.g., request_minimal.json)
    //  */
    // private function new_test_artifact( $filename ) {
    //     $jsonstr = file_get_contents( $this::PATH_TO_MODULE_TEST_BASE . "/fixtures/" . $filename );
    //     $json = json_decode( $jsonstr );
    //     return new CDSArtifact( $json );
    // }


    // ----- Internal object tests ---------------------------------------------


    /** @test
     * @expectedException ArgumentCountError
    */
    public function test_constructor_empty()
    {
        $resource = new CDSResource();
    }


    /**
     *  @test make sure that the we are still returning the correct URI path
     *
     */
    public function test_get_uri_path()
    {
        $this->assertEquals( CDSResource::get_uri_path(), "/cds_api" );
    }


}
