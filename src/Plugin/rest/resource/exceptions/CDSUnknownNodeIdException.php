<?php

namespace Drupal\cds_api\Plugin\rest\resource\exceptions;
/**
 * A standard CDS Exception when an id is specified in the request that is
 * not known to Drupal 8
 */
class CDSUnknownNodeIdException extends CDSException
{
    /**
     * Constructor
     *
     * @param integer $id
     *      The requested ID
     */
    public function __construct( $id )
    {
        parent::__construct( 404, t(CDSException::UNKNOWN_NODE_ID, ['@id' => $id]));
    }

}


