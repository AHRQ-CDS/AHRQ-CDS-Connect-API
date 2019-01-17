<?php

namespace Drupal\Tests\cds_api\Unit;

use Drupal\Tests\cds_api\Unit\CDSUtils;

use Drupal\Component\Utility\Xss;
use PHPUnit\Framework\TestCase;

// use Drupal\cds_api\Plugin\rest\resource\CDSArtifCDact;
// use Drupal\cds_api\Plugin\rest\resource\CDSResource;
use Drupal\cds_api\Plugin\rest\resource\CDSSchema;

/**
 * CDSSchemaTest
 * @group restUnit
 */
class CDSSchemaTest extends TestCase
{
    // ----- Sanitization tests ---------------------------------------------


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
        $this->assertEquals( CDSSchema::sanitize_string( "" ), "" );
    }


    /** @test
     * @dataProvider _test_string_provider
    */
    public function test_sanitize_string_strict( $payload, $expected, $expectedAdmin )
    {
        $this->assertEquals ( CDSSchema::sanitize_string( $payload ), $expected );
    }


    /** @test
     * @dataProvider _test_string_provider
    */
    public function test_sanitize_strings_permissive( $payload, $expected, $expectedAdmin )
    {

        $this->assertEquals ( CDSSchema::sanitize_string( $payload, true ), $expectedAdmin );

    }

}
