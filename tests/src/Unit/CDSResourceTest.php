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


    // ----- Validation tests ---------------------------------------------


    /**
     * @dataProvider _test_string_provider
     */
    public function test_play_with_xss_filter($payload, $expected)
    {
        $this->assertEquals( XSS::filter( $payload ), $expected );
    }


    /**
     * @dataProvider _test_string_provider
     */
    public function test_play_with_xss_filterAdmin($payload, $expected, $expectedAdmin)
    {
        $this->assertEquals( XSS::filterAdmin( $payload ), $expectedAdmin );
    }


    public function _test_string_provider()
    {
        $plain = "ABCDEFGHIJklmnopqrstuvwxyz12345!@#$%^*()_+-=`~\"'?/,.";
        $complexHtml = "<ol><li>123</li><li>456</li><ol><li>1</li></ol></ol>";
        $complex2 = '<span style="color:red;float:around;text-decoration:blink;">Text</span>';
        $unclosed = '<h1>abc';
        $sqlinjection = 'SELECT * FROM Users WHERE UserId = 105 OR 1=1;';

        return [
            // $payload, $expected, $expectedAdmin

            // safe text
                [ $plain,                   $plain,                     $plain ],
                [ "<>&",                    '&lt;&gt;&amp;',            '&lt;&gt;&amp;' ],
                [ "3<5 & 5 > 3",            '3&lt;5 &amp; 5 &gt; 3',    '3&lt;5 &amp; 5 &gt; 3' ],
                [ "<h1>abc</h1>",           "abc",                      "<h1>abc</h1>" ],       // note that if this from a JSON file, both $expected and $expectedAdmin return "<h1>abc</h1>"
                [ "<em>abc</em>",           "<em>abc</em>",             "<em>abc</em>" ],
                [ $complexHtml,             $complexHtml,               $complexHtml ],
                [ '"',                      '"',                        '"'],
                [ '<a href="a">abc</a>',    '<a href="a">abc</a>',      '<a href="a">abc</a>' ],
                [ $complex2,                'Text',                     '<span>Text</span>' ],
                [ ':simple_smile:',         ':simple_smile:',           ':simple_smile:' ],
                [ "😀",                     "😀",                        "😀" ],
                [ "Arabic عربى",           "Arabic عربى",               "Arabic عربى" ],
                [ "Chinese 中文",           "Chinese 中文",               "Chinese 中文" ],
                [ "fenêtre de l'école française", "fenêtre de l'école française", "fenêtre de l'école française" ],
                [ "Hebrew עִברִית",          "Hebrew עִברִית",               "Hebrew עִברִית" ],

            // questionable output of safe text
            //  @todo hk maybe look into HTMLPurifier which takes care of these in a safer way
                [ "<h1>abc",                "abc",                      "<h1>abc" ],    // expected "<h1>abc</h1>" for admin
                [ '\"',                     '\"',                       '\"'],          // expected '"'
                [ '<a href=\"a\">abc</a>',  '<a>abc</a>',               '<a>abc</a>' ], // expected '<a href="a">abc</a>' for admin

            // unsafe text
                [ '<img src="javascript:evil();" onload="evil();" /><h1>abc</h1>',
                    'abc',
                    '<img src="evil();" /><h1>abc</h1>' ],
                [ '<script>let a = 1;</script>',
                    'let a = 1;',
                    'let a = 1;' ],
                [ '<script>',
                    '',
                    '' ],
                [ '</script>',
                    '',
                    '' ],

            // expected inability to find clean unsafe
                [ $sqlinjection, $sqlinjection, $sqlinjection ],

            // questionable output of unsafe text
            //  @todo hk maybe look into HTMLPurifier which takes care of these in a safer way
                [ "<script>eval('2 + 2');</script>",
                    "eval('2 + 2');",
                    "eval('2 + 2');" ],    // expected ""
        ];
    }


    /** @test */
    public function test_sanitize_string_empty()
    {
        // @todo hk need a few more of these tests from reading in from a JSON file
        $this->assertEquals( CDSResource::sanitize_string( "" ), "" );
    }


    /** @test
     * @dataProvider _test_string_provider
    */
    public function test_sanitize_string_strict( $payload, $expected, $expectedAdmin )
    {
        $this->assertEquals ( CDSResource::sanitize_string( $payload ), $expected );
    }


    /** @test
     * @dataProvider _test_string_provider
    */
    public function test_sanitize_strings_permissive( $payload, $expected, $expectedAdmin )
    {

        $this->assertEquals ( CDSResource::sanitize_string( $payload, true ), $expectedAdmin );

    }

}
