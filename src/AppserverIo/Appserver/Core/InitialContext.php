<?php
/**
 * \AppserverIo\Appserver\Core\InitialContext
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

namespace AppserverIo\Appserver\Core;

use AppserverIo\Storage\StorageInterface;
use AppserverIo\Appserver\Core\InitialContext\ContextKeys;
use AppserverIo\Psr\ApplicationServer\ContextInterface;
use AppserverIo\Psr\ApplicationServer\Configuration\SystemConfigurationInterface;

/**
 * Initial context implementation.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 *
 * @property \Psr\Log\LoggerInterface[] $loggers The collection of loggers we know
 */
class InitialContext implements ContextInterface
{

    /**
     * The storage instance
     *
     * @var \AppserverIo\Storage\StorageInterface
     */
    protected $storage;

    /**
     * The server's system logger instance.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $systemLogger;

    /**
     * The server's classLoading instance
     *
     * @var \AppserverIo\Appserver\Core\SplClassLoader
     */
    protected $classLoader;

    /**
     * Initializes the context with the connection to the storage backend.
     *
     * @param \AppserverIo\Psr\ApplicationServer\Configuration\SystemConfigurationInterface $systemConfiguration The system configuration
     */
    public function __construct(SystemConfigurationInterface $systemConfiguration)
    {

        // initialize the storage
        $initialContextNode = $systemConfiguration->getInitialContext();
        $storageNode = $initialContextNode->getStorage();
        $reflectionClass = $this->newReflectionClass($storageNode->getType());

        // create the storage instance
        /** @var StorageInterface $storage */
        $storage = $reflectionClass->newInstance();

        // append the storage servers registered in system configuration
        /** @var \AppserverIo\Appserver\Core\Api\Node\StorageServerNode $storageServer */
        foreach ($storageNode->getStorageServers() as $storageServer) {
            $storage->addServer($storageServer->getAddress(), $storageServer->getPort(), $storageServer->getWeight());
        }

        // add the storage to the initial context
        $this->setStorage($storage);

        // attach the system configuration to the initial context
        $this->setSystemConfiguration($systemConfiguration);
    }

    /**
     * Returns the storage instance.
     *
     * @param \AppserverIo\Storage\StorageInterface $storage A storage instance
     *
     * @return \AppserverIo\Storage\StorageInterface The storage instance
     */
    public function setStorage(StorageInterface $storage)
    {
        return $this->storage = $storage;
    }

    /**
     * Returns the storage instance.
     *
     * @return \AppserverIo\Storage\StorageInterface The storage instance
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * Adds the system configuration to the initial context.
     *
     * @param \AppserverIo\Psr\ApplicationServer\Configuration\SystemConfigurationInterface $systemConfiguration The system configuration
     *
     * @return void
     */
    public function setSystemConfiguration(SystemConfigurationInterface $systemConfiguration)
    {
        $this->setAttribute(ContextKeys::SYSTEM_CONFIGURATION, $systemConfiguration);
    }

    /**
     * Returns the system configuration.
     *
     * @return \AppserverIo\Psr\ApplicationServer\Configuration\SystemConfigurationInterface The system configuration
     */
    public function getSystemConfiguration()
    {
        return $this->getAttribute(ContextKeys::SYSTEM_CONFIGURATION);
    }

    /**
     * Stores the passed key value pair in the initial context.
     *
     * @param string $key   The key to store the value under
     * @param mixed  $value The value to add to the initial context
     *
     * @return void
     */
    public function setAttribute($key, $value)
    {
        $this->storage->set($key, $value);
    }

    /**
     * Returns the value with the passed key from the initial context.
     *
     * @param string $key The key of the value to return
     *
     * @return mixed The value stored in the initial context
     */
    public function getAttribute($key)
    {
        return $this->storage->get($key);
    }

    /**
     * Removes the attribute with the passed key from the initial context.
     *
     * @param string $key The key of the value to delete
     *
     * @return void
     */
    public function removeAttribute($key)
    {
        $this->storage->remove($key);
    }

    /**
     * Returns a reflection class instance for the passed class name.
     *
     * @param string $className The class name to return the reflection instance for
     *
     * @return \ReflectionClass The reflection instance
     */
    public function newReflectionClass($className)
    {
        return new \ReflectionClass($className);
    }

    /**
     * Returns a new instance of the passed class name.
     *
     * @param string $className The fully qualified class name to return the instance for
     * @param array  $args      Arguments to pass to the constructor of the instance
     *
     * @return object The instance itself
     */
    public function newInstance($className, array $args = array())
    {
        // create and return a new instance
        $reflectionClass = $this->newReflectionClass($className);
        return $reflectionClass->newInstanceArgs($args);
    }

    /**
     * Returns a new instance of the passed API service.
     *
     * @param string $className The API service class name to return the instance for
     *
     * @return \AppserverIo\Psr\ApplicationServer\ServiceInterface The service instance
     */
    public function newService($className)
    {
        return $this->newInstance($className, array($this));
    }

    /**
     * Set's the system logger instance.
     *
     * @param \Psr\Log\LoggerInterface $systemLogger The system logger
     *
     * @return void
     */
    public function setSystemLogger($systemLogger)
    {
        $this->systemLogger = $systemLogger;
    }

    /**
     * Set's logger array
     *
     * @param array $loggers The loggers array to set
     *
     * @return void
     */
    public function setLoggers(array $loggers)
    {
        $this->loggers = $loggers;
    }

    /**
     * Return
     *
     * @return array
     */
    public function getLoggers()
    {
        return $this->loggers;
    }

    /**
     * Gets the logger by given name
     *
     * @param string $loggerName the loggers name
     *
     * @return \Psr\Log\LoggerInterface|null The logger instance
     */
    public function getLogger($loggerName)
    {
        if (isset($this->loggers[$loggerName])) {
            return $this->loggers[$loggerName];
        }
    }

    /**
     * Returns the system logger instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getSystemLogger()
    {
        return $this->systemLogger;
    }
}
