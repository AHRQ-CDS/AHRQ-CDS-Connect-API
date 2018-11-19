<?php

namespace Drupal\cds_api\Plugin\rest\resource\exceptions;

/**
 * A standard CDS security Exception when a request is made for
 * a resource that the user is not authorized to use
 *
 */
class CDSNotAuthorizedException extends CDSException
{
    /**
     * Constructor
     *
     * @param integer $id
     *      The drupal node ID that was requested
     */
    public function __construct( integer $id=null )
    {
        $id = ($id) ? $id : "";
        parent::__construct( 403, CDSException::NOT_AUTHORIZED, ['@id' => $id ] );
    }

}


