<?php

namespace Drupal\cds_api\Plugin\rest\resource\exceptions;

/**
 * A standard CDS Exception when a JSON document does not
 * conform to the CDS schema
 *
 * POST and PATCH both must have conformant JSON in their request
 */
class CDSNonconformantJsonException extends CDSException
{
    /**
     * constructor
     *
     * @param string $errors
     *      A string containing specific error information
     */
    public function __construct( string $errors )
    {
        CDSException::__construct( 400, t( CDSException::NONCONFORMANT_JSON, ['@errors' => $errors ] ) );
    }

}


