<?php

/**
 * \AppserverIo\Appserver\Core\Api\ExtensionInjectorParameterTrait
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

namespace AppserverIo\Appserver\Core\Api;

use AppserverIo\Description\Annotations as DI;

/**
 * This trait enables the usage of extensionType attributes on any node
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
trait ExtensionInjectorParameterTrait
{
    /**
     * The extension type string we have to get our injector from
     *
     * @var string $extensionType
     * @DI\Mapping(nodeType="string")
     */
    protected $extensionType;

    /**
     * Instance of the
     *
     * @var \AppserverIo\Server\Configuration\Extension\InjectorInterface $instance
     */
    private $injector;

    /**
     * Will init the injector from the given extensionType
     *
     * @return null
     * @throws \Exception
     */
    protected function initExtensionType()
    {
        // We have to get the class of our injector
        $class = '';
        if (!class_exists($class = $this->extensionType) &&
            !class_exists($class = strstr($this->extensionType, '(', true))) {
            throw new \Exception('Unknown injector class ' . $class);
        }

        // We also have to get the parameter
        $parameters = array();
        preg_match('`\((.+)\)`', $this->getExtensionType(), $parameters);

        // Clean them up and separate them
        $parameterString = str_replace(array(' ', '\''), array(''), $parameters[1]);
        $clearParameters = explode(',', $parameterString);

        // Instantiate the injector and  init it
        $this->injector = new $class($clearParameters[0], $clearParameters[1], $clearParameters[2]);
        $this->injector->init();
    }

    /**
     * Getter for the injector, will lazy-init it
     *
     * @return \AppserverIo\Server\Configuration\Extension\InjectorInterface
     */
    public function getInjector()
    {
        if (!isset($this->injector)) {
            $this->initExtensionType();
        }
        return $this->injector;
    }

    /**
     * Will return true if the class has a filled extensionType attribute and therefor an injector
     *
     * @return boolean
     */
    public function hasInjector()
    {
        return !empty($this->extensionType);
    }

    /**
     * Will return the data the defined injector creates
     *
     * @return mixed
     */
    public function getInjection()
    {
        return $this->getInjector()->extract();
    }

    /**
     * Getter for extensionType attribute
     *
     * @return string
     */
    public function getExtensionType()
    {
        return $this->extensionType;
    }
}
