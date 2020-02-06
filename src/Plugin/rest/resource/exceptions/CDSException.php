<?php

namespace Drupal\cds_api\Plugin\rest\resource\exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;


/**
 * If necessary, implement a no-op version of t() for phpunit tests
 *
 * The `t()` function enables translation of the parameter $string.  However, in many phpunit tests, this facility is
 * not needed, thus the need for this workaround:  if t() exists, then don't do anything, but if it doesn't exist, make it
 * simply return the $string parameter.  This enables the code to work in production as well as simplify kernel testing in phpunit
 *
 * @return the new no-op t() function, if needed
 */
if(!function_exists('t')) {
    /**
     * Implement a no-op version of t() for phpunit tests
     *
     * For an explaination for this function, see the description above
     *
     * @param string $string
     *  The string to be returned (since we're not going to translate it)
     *
     * @return string
     */
    function t(string $string, $arr=null) : string {
        return $string;
    }
}


/**
 * CDSException is the base class for all CDS-specific exceptions
 *
 * This class serves 2 purposes:
 * 1. provides custom exception behavior useful for using CDS REST API (currently,
 *      it is only returning a ```{ "message": $msg }``` block, but the intention is to make this more robust for the community in the future )
 * 2. provides a "directory" of standardized exception text for use in the subclasses
 *      as defined in the exception series constants below, so that all exceptions will be consistent from the API
 *
 * @example CDSArtifact::load_json_post()    Contains a good example of how to throw a CDS-specific exception (i.e., CDSNonconformantJsonException)
 *      complete with tailored error messages.  Basically (from the load_json_post() example):
 *      ```
 *          $error = CDSSchema::get_schema_validation_errors_as_string();
 *          throw new CDSNonconformantJsonException( $error );
 *      ```
 *      produces the JSON (e.g.,):
 *      ```
 *          {
 *              "message": "Error: CDS-1003 - The JSON request does not conform to the CDS Schema at GET /cds_api.  Schema errors:  \
 *                             [title] The property title is required\n\
 *                             [experimental] String value found, but a boolean is required\n"
 *          }
 *      ```
 *
 */
class CDSException extends HttpException // implements HttpExceptionInterface
{
    /** constants */

    // ----- 1000 series exceptions — client request errors ----- ----- ----- ----- ----- ----- ----- -----

    /** text for CDSUnknownNodeIdException */
    public const UNKNOWN_NODE_ID = "Error: CDS-1001 - Artifact with ID @id was not found";

    /** text for CDSNodeIdRequiredException */
    public const NODE_ID_REQUIRED = "Error: CDS-1002 - An ID is required for @verb @path";

    /** text for CDSNonconformantJsonException */
    public const NONCONFORMANT_JSON = 'Error: CDS-1003 - The JSON request does not conform to the CDS Schema at GET /cds_api.  Schema errors:  @errors';

    // ----- 2000 series exceptions — security errors ----- ----- ----- ----- ----- ----- ----- ----- -----

    /** text for CDSNotAuthenticatedException
     *  @todo need to implement this
     */

     // public const NOT_AUTHENTICATED = "Error: CDS-2001 - Authentication is required for @verb @path";

     /** text for CDSNotAuthorizedException */
    public const NOT_AUTHORIZED = "Error: CDS-2002 - Not authorized to operate on artifact @id";

    // ----- Constructor ----- ----- ----- ----- ----- ----- ----- ----- -----

    /** constructor
     *
     *  While this class can be used to send custom exceptions, it is intended that the spcialized Exceptions classes
     *  in this namespace be used instead for consistency.
     * @todo Currently, only 400 series errors can be used for custom messages, other series are intercepted at a higher priority.  This needs to be resolved.
     *
     * @param integer $statusCode
     *      The HTTP response code that conforms to the official documentation at:
     *      https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     * @param string $user_message
     *      The user message to send to the caller
     */
    public function __construct( $statusCode, $user_message = null)
    {
        parent::__construct( $statusCode,  $user_message );
    }

}

