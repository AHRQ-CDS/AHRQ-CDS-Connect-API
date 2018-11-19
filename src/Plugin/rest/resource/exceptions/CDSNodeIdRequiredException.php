<?php

namespace Drupal\cds_api\Plugin\rest\resource\exceptions;

/**
 * A standard CDS Exception when a Drupal 8 node ID is expected
 * in a request, but none is provided
 *
 * GET and PATCH both require a node ID, which is assigned and returned when
 * a POST is successfully completed.
 */
class CDSNodeIdRequiredException extends CDSException
{
    /**
     * constructor
     *
     * @param string $verb
     *      One of ["GET","POST","PATCH"]; if none is specified,
     *      the method can figure it out
     * @param string $path
     *      The URI in the request; if none is specified,
     *      the method can figure it out
     */
    public function __construct( string $verb=null, string $path=null )
    {
        $verb = ($verb) ? $verb : \Drupal::request()->getMethod();
        $path = ($path) ? $path : \Drupal::request()->getRequestUri();
        parent::__construct( 404, CDSException::NODE_ID_REQUIRED, ['@verb' => $verb, '@path' => $path ] );
    }

}


