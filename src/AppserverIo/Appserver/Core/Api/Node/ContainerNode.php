<?php

/**
 * \AppserverIo\Appserver\Core\Api\Node\ContainerNode
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
 * @author    Johann Zelger <jz@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Appserver\Core\Api\Node;

use AppserverIo\Description\Api\Node\AbstractNode;
use AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface;

/**
 * DTO to transfer a container.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @author    Johann Zelger <jz@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class ContainerNode extends AbstractNode implements ContainerConfigurationInterface
{

    /**
     * A class loader trait.
     *
     * @var \AppserverIo\Appserver\Core\Api\Node\ClassLoadersNodeTrait
     */
    use ClassLoadersNodeTrait;

    /**
     * The container's name.
     *
     * @var string
     * @AS\Mapping(nodeType="string")
     */
    protected $name;

    /**
     * The container's class name.
     *
     * @var string
     * @AS\Mapping(nodeType="string")
     */
    protected $type;

    /**
     * The container's factory class name.
     *
     * @var string
     * @AS\Mapping(nodeType="string")
     */
    protected $factory;

    /**
     * The thread class name that start's the container.
     *
     * @var string
     * @AS\Mapping(nodeType="string")
     */
    protected $threadType;

    /**
     * En-/Disables application application provisioning.
     *
     * @var boolean
     * @AS\Mapping(nodeType="boolean")
     */
    protected $provisioning = true;

    /**
     * Container description
     *
     * @var \AppserverIo\Appserver\Core\Api\Node\DescriptionNode
     * @AS\Mapping(nodeName="description", nodeType="AppserverIo\Appserver\Core\Api\Node\DescriptionNode")
     */
    protected $description;

    /**
     * The receiver used to start the container's socket.
     *
     * @var \AppserverIo\Appserver\Core\Api\Node\ReceiverNode
     * @AS\Mapping(nodeName="receiver", nodeType="AppserverIo\Appserver\Core\Api\Node\ReceiverNode")
     */
    protected $receiver;

    /**
     * The upstreams used in container
     *
     * @var array
     * @AS\Mapping(nodeName="upstreams/upstream", nodeType="array", elementType="AppserverIo\Appserver\Core\Api\Node\UpstreamNode")
     */
    protected $upstreams;

    /**
     * The servers used in container
     *
     * @var array
     * @AS\Mapping(nodeName="servers/server", nodeType="array", elementType="AppserverIo\Appserver\Core\Api\Node\ServerNode")
     */
    protected $servers;

    /**
     * The host configuration information.
     *
     * @var \AppserverIo\Appserver\Core\Api\Node\HostNode
     * @AS\Mapping(nodeName="host", nodeType="AppserverIo\Appserver\Core\Api\Node\HostNode")
     */
    protected $host;

    /**
     * The deployment configuration information.
     *
     * @var \AppserverIo\Appserver\Core\Api\Node\DeploymentNode
     * @AS\Mapping(nodeName="deployment", nodeType="AppserverIo\Appserver\Core\Api\Node\DeploymentNode")
     */
    protected $deployment;

    /**
     * Returns the unique container name.
     *
     * @return string The unique container name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the container's class name.
     *
     * @return string The container's class name
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the container's factory class name.
     *
     * @return string The container's factory class name
     */
    public function getFactory()
    {
        return $this->factory;
    }

    /**
     * Returns the thread class name that start's the containere.
     *
     * @return string The thread class name that start's the container
     */
    public function getThreadType()
    {
        return $this->threadType;
    }

    /**
     * Returns TRUE if application provisioning for the container is enabled, else FALSE.
     *
     * @return boolean TRUE if application provisioning is enabled, else FALSE
     */
    public function getProvisioning()
    {
        return $this->provisioning;
    }

    /**
     * Returns the receiver description.
     *
     * @return \AppserverIo\Appserver\Core\Api\Node\DescriptionNode The receiver description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns the host configuration information.
     *
     * @return \AppserverIo\Psr\ApplicationServer\Configuration\HostConfigurationInterface The host configuration information
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Returns the deployment configuration information.
     *
     * @return \AppserverIo\Psr\ApplicationServer\Configuration\DeploymentConfigurationInterface The deployment configuration information
     */
    public function getDeployment()
    {
        return $this->deployment;
    }

    /**
     * Return's all server nodes
     *
     * @return array
     */
    public function getServers()
    {
        return $this->servers;
    }

    /**
     * Returns the servers as array with the server name as key.
     *
     * @return array The array with the servers
     */
    public function getServersAsArray()
    {

        // initialize the array for the servers
        $servers = array();

        // iterate over all found servers and assemble the array
        /** @var \AppserverIo\Appserver\Core\Api\Node\ServerNodeInterface $server */
        foreach ($this->getServers() as $server) {
            $servers[$server->getName()] = $server;
        }

        // return the array with the servers
        return $servers;
    }

    /**
     * Returns the server with the passed name.
     *
     * @param string $name The name of the server to return
     *
     * @return \AppserverIo\Appserver\Core\Api\Node\ServerNodeInterface The server node matching the passed name
     */
    public function getServer($name)
    {

        // try to match one of the server names with the passed name
        /** @var \AppserverIo\Appserver\Core\Api\Node\ServerNodeInterface $server */
        foreach ($this->getServers() as $server) {
            if ($name === $server->getName()) {
                return $server;
            }
        }
    }

    /**
     * Attaches the passed server node.
     *
     * @param \AppserverIo\Appserver\Core\Api\Node\ServerNodeInterface $server The server node to attach
     *
     * @return void
     */
    public function attachServer(ServerNodeInterface $server)
    {
        $this->servers[$server->getPrimaryKey()] = $server;
    }

    /**
     * Return's all upstream nodes
     *
     * @return array
     */
    public function getUpstreams()
    {
        return $this->upstreams;
    }

    /**
     *This method merges the passed container node into this one.
     *
     * @param \AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface $containerNode The container node to merge
     *
     * @return void
     */
    public function merge(ContainerConfigurationInterface $containerNode)
    {
        // iterate over this container server nodes
        /** @var \AppserverIo\Appserver\Core\Api\Node\ServerNodeInterface $serverNode */
        foreach ($this->getServers() as $serverNode) {
            // try to match with the server names of the passed container
            /** @var \AppserverIo\Appserver\Core\Api\Node\ServerNodeInterface $serverNodeToMerge */
            foreach ($containerNode->getServers() as $serverNodeToMerge) {
                if (fnmatch($serverNodeToMerge->getName(), $serverNode->getName())) {
                    $serverNode->merge($serverNodeToMerge);
                } else {
                    $this->attachServer($serverNode);
                }
            }
        }
    }
}
