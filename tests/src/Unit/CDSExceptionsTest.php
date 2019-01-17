<?php
/**
 * A set of unit tests to test all of the standard CDS Exceptions classes
 */

namespace Drupal\Tests\cds_api\Unit;

use Drupal\Tests\cds_api\Unit\CDSUtils;

use Drupal\cds_api\Plugin\rest\resource\CDSArtifact;
use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSException;
use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSNodeIdRequiredException;
use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSNonconformantJsonException;
use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSNotAuthorizedException;
use Drupal\cds_api\Plugin\rest\resource\exceptions\CDSUnknownNodeIdException;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for all of the CDSException-based classes
 *
 * @group restUnit
 */
class CDSExceptionsTest extends TestCase
{

    // ----- CDSExcpetion tests ----- ----- ----- ----- ----- ----- ----- -----

    /** @test Tests CDSException
     */
    public function test_CDSException_constructor()
    {
        $ex = new CDSException( 400, "abc" );
        $this->assertEquals( $ex->getStatusCode(), 400 );
        $this->assertEquals( $ex->getMessage(), "abc" );
        $this->expectException(CDSException::class);
        throw $ex;
    }


    // ----- CDSNodeIdRequiredException tests ----- ----- ----- ----- ----- ----- ----- -----


    /** @test Tests CDSNodeIdRequiredException
     * @todo This test causes this error:  `\Drupal::$container is not initialized yet. \Drupal::setContainer() must becalled with a real container.`
     */
    // public function test_CDSNodeIdRequiredException()
    // {
    //     $ex = new CDSNodeIdRequiredException();
    //     $this->assertEquals( $ex->getStatusCode(), 404 );
    //     $this->assertContains( "Error: CDS-1002", $ex->getMessage() );
    //     $this->expectException(CDSNodeIdRequiredException::class);
    //     throw $ex;
    // }


    // ----- CDSNonconformantJsonException tests ----- ----- ----- ----- ----- ----- ----- -----


    /** @test Tests CDSNonconformantJsonException
     */
    public function test_CDSNonconformantJsonException()
    {
        $ex = new CDSNonconformantJsonException( "abc" );
        $this->assertEquals( $ex->getStatusCode(), 400 );
        $this->assertContains( "Error: CDS-1003", $ex->getMessage() );
        $this->expectException(CDSNonconformantJsonException::class);
        throw $ex;
    }


    // ----- CDSNotAuthorizedException tests ----- ----- ----- ----- ----- ----- ----- -----


    /** @test Tests CDSNotAuthorizedException
     * @todo this test causes the error `\Drupal::$container is not initialized yet. \Drupal::setContainer() must becalled with a real container.`
     */
    // public function test_CDSNotAuthorizedException()
    // {
    //     $ex = new CDSNotAuthorizedException( 1 );
    //     $this->assertEquals( $ex->getStatusCode(), 403 );
    //     $this->assertContains( "Error: CDS-2002", $ex->getMessage() );
    //     $this->expectException(CDSNotAuthorizedException::class);
    //     throw $ex;
    // }


    // ----- CDSUnknownNodeIdException tests ----- ----- ----- ----- ----- ----- ----- -----


    /** @test Tests CDSUnknownNodeIdException
     */
    public function test_CDSUnknownNodeIdException()
    {
        $ex = new CDSUnknownNodeIdException( 1234 );
        $this->assertEquals( $ex->getStatusCode(), 404 );
        $this->assertContains( "Error: CDS-1001", $ex->getMessage() );
        // $this->assertContains( "1234", $ex->getMessage() );
        $this->expectException(CDSUnknownNodeIdException::class);
        throw $ex;
    }

}
