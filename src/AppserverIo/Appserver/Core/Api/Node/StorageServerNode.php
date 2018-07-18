<?php

/**
 * \AppserverIo\Appserver\Core\Api\Node\StorageServerNode
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Appserver\Core\Api\Node;

use AppserverIo\Description\Annotations as DI;
use AppserverIo\Description\Api\Node\AbstractNode;

/**
 * DTO to transfer server information.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class StorageServerNode extends AbstractNode
{

    /**
     * The server's IP address.
     *
     * @var string
     * @DI\Mapping(nodeType="string")
     */
    protected $address;

    /**
     * The server's port.
     *
     * @var integer
     * @DI\Mapping(nodeType="integer")
     */
    protected $port;

    /**
     * The server's weight.
     *
     * @var integer
     * @DI\Mapping(nodeType="integer")
     */
    protected $weight;

    /**
     * Returns the IP address the server listens to.
     *
     * @return string the IP address the server listens to
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Returns the port the server listens to.
     *
     * @return string the port the server listens to
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Returns weight the server has in the storage cluster.
     *
     * @return integer The weight the server has in the storage cluster
     */
    public function getWeight()
    {
        return $this->weight;
    }
}
