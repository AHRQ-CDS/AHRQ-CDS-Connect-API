<?php

namespace Drupal\cds_api\Plugin\rest\resource;

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
 * Utility class to simply working with the CDS Schema.
 *
 * This class provides a set of utilities for working with the CDS Schema
 * including validation, sanitization, and Exception parsing
 */
class CDSSchema
{
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
    public static function sanitize_string($string, bool $permissive = false) : ?string
    {
        if ($string === null) {
          return null;
        }
        elseif ($string === "") {
            return "";
        } else {
            if ($permissive) {
                return XSS::filterAdmin($string);
            } else {
                return XSS::filter($string);
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
    public static function validate_json($json, bool $applyDefaults = true) : Validator
    {
        $schema = CDSArtifact::get_schema();
        $validator = new Validator;
        $constraints = $applyDefaults ? Constraint::CHECK_MODE_APPLY_DEFAULTS : Constraint::CHECK_MODE_NONE;
        $validator->validate($json, $schema, $constraints);
        return $validator;
    }


    // ----- Exception Messages ----- ----- ----- ----- -----


    /**
     * returns the validation error object as an array of errors
     *      of the form [key] => message
     *
     * @param $validator
     *    The validator that was used for validation
     *
     * @returns the validation error array
     */
    public static function get_schema_validation_errors_as_array($validator)
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
    public static function get_schema_validation_errors_as_string($validator)
    {
        $retval = "";
        foreach ($validator->getErrors() as $error) {
            $retval = $retval . sprintf("[%s] %s\n", $error['property'], $error['message']);
        }
        return $retval;
    }
}
