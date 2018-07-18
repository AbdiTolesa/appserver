<?php

/**
 * AppserverIo\Appserver\Core\Api\Node\AuthenticationsNodeTrait
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Appserver\Core\Api\Node;

use AppserverIo\Description\Annotations as DI;

/**
 * This trait is used to give any node class the possibility to manage authentication nodes
 * which might be child elements of it.
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
trait AuthenticationsNodeTrait
{

    /**
     * The authentications specified within the parent node
     *
     * @var array
     * @DI\Mapping(nodeName="authentications/authentication", nodeType="array", elementType="AppserverIo\Appserver\Core\Api\Node\AuthenticationNode")
     */
    protected $authentications = array();

    /**
     * Will return the authentications array.
     *
     * @return array The array with the authentications
     */
    public function getAuthentications()
    {
        return $this->authentications;
    }

    /**
     * Will return the authentication node with the specified definition and if nothing could
     * be found we will return false.
     *
     * @param string $uri The URI of the authentication in question
     *
     * @return \AppserverIo\Appserver\Core\Api\Node\AuthenticationNode|boolean The requested authentication node
     */
    public function getAuthentication($uri)
    {

        // iterate over all authentications
        /** @var \AppserverIo\Appserver\Core\Api\Node\AuthenticationNode $authenticationNode */
        foreach ($this->getAuthentications() as $authenticationNode) {
            // if we found one with a matching URI we will return it
            if ($authenticationNode->getUri() === $uri) {
                return $authenticationNode;
            }
        }

        // we did not find anything
        return false;
    }

    /**
     * Returns the authentications as an associative array.
     *
     * @return array The array with the sorted authentications
     */
    public function getAuthenticationsAsArray()
    {

        // initialize the array for the authentications
        $authentications = array();

        // iterate over the authentication nodes and sort them into an array
        /** @var \AppserverIo\Appserver\Core\Api\Node\AuthenticationNode $authenticationNode */
        foreach ($this->getAuthentications() as $authenticationNode) {
            $authentications[$authenticationNode->getUri()] = $authenticationNode->getParamsAsArray();
        }

        // return the array
        return $authentications;
    }
}
