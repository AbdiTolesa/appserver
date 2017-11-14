<?php

/**
 * \AppserverIo\Appserver\PersistenceContainer\BeanLocator
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

namespace AppserverIo\Appserver\PersistenceContainer;

use AppserverIo\Psr\Di\ProviderInterface;
use AppserverIo\Psr\Di\ObjectManagerInterface;
use AppserverIo\Psr\EnterpriseBeans\BeanContextInterface;
use AppserverIo\Psr\EnterpriseBeans\ResourceLocatorInterface;
use AppserverIo\Psr\EnterpriseBeans\EnterpriseBeansException;
use AppserverIo\Psr\EnterpriseBeans\Description\MessageDrivenBeanDescriptorInterface;
use AppserverIo\Psr\EnterpriseBeans\Description\StatefulSessionBeanDescriptorInterface;
use AppserverIo\Psr\EnterpriseBeans\Description\SingletonSessionBeanDescriptorInterface;
use AppserverIo\Psr\EnterpriseBeans\Description\StatelessSessionBeanDescriptorInterface;
use AppserverIo\Psr\EnterpriseBeans\Description\BeanDescriptorInterface;

/**
 * The bean resource locator implementation.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class BeanLocator implements ResourceLocatorInterface
{

    /**
     * Runs a lookup for the session bean with the passed class name and
     * session ID.
     *
     * If the passed class name is a session bean an instance
     * will be returned.
     *
     * @param \AppserverIo\Psr\EnterpriseBeans\BeanContextInterface $beanManager The bean manager instance
     * @param string                                                $className   The name of the session bean's class
     * @param string                                                $sessionId   The session ID
     * @param array                                                 $args        The arguments passed to the session beans constructor
     *
     * @return object The requested session bean
     */
    public function lookup(BeanContextInterface $beanManager, $className, $sessionId = null, array $args = array())
    {

        // load the object manager
        /** @var \AppserverIo\Psr\Di\ObjectManagerInterface $objectManager */
        $objectManager = $beanManager->getApplication()->search(ObjectManagerInterface::IDENTIFIER);

        // query whether or not an object descriptor is available
        if ($objectManager->getObjectDescriptors()->has($className)) {
            // load the bean descriptor
            $descriptor = $objectManager->getObjectDescriptors()->get($className);

            // query whether we've a SFSB
            if ($descriptor instanceof StatefulSessionBeanDescriptorInterface) {
                // try to load the stateful session bean from the bean manager
                if ($instance = $beanManager->lookupStatefulSessionBean($sessionId, $className)) {
                    // load the object manager and re-inject the dependencies
                    /** @var \AppserverIo\Psr\Di\ProviderInterface $provider */
                    $provider = $beanManager->getApplication()->search(ProviderInterface::IDENTIFIER);
                    $provider->injectDependencies($instance, $sessionId);

                    // we've to check for post-detach callbacks
                    foreach ($descriptor->getPostDetachCallbacks() as $postDetachCallback) {
                        $instance->$postDetachCallback();
                    }

                    // return the instance
                    return $instance;
                }

                // if not create a new instance and return it
                $instance = $beanManager->newInstance($className, $sessionId, $args);

                // we've to check for post-construct callbacks
                foreach ($descriptor->getPostConstructCallbacks() as $postConstructCallback) {
                    $instance->$postConstructCallback();
                }

                // return the instance
                return $instance;
            }

            // query whether we've a SSB
            if ($descriptor instanceof SingletonSessionBeanDescriptorInterface) {
                // try to load the singleton session bean from the bean manager
                if ($instance = $beanManager->lookupSingletonSessionBean($className)) {
                    // load the object manager and re-inject the dependencies
                    /** @var \AppserverIo\Psr\Di\ProviderInterface $provider */
                    $provider = $beanManager->getApplication()->search(ProviderInterface::IDENTIFIER);
                    $provider->injectDependencies($instance, $sessionId);

                    // we've to check for post-detach callbacks
                    foreach ($descriptor->getPostDetachCallbacks() as $postDetachCallback) {
                        $instance->$postDetachCallback();
                    }

                    // return the instance
                    return $instance;
                }

                // singleton session beans MUST extends \Stackable
                if (is_subclass_of($className, '\Stackable') === false) {
                    throw new EnterpriseBeansException(sprintf('Singleton session bean %s MUST extend \Stackable', $className));
                }

                // if not create a new instance and return it
                $instance = $beanManager->newSingletonSessionBeanInstance($className, $sessionId, $args);

                // add the singleton session bean to the container
                $beanManager->getSingletonSessionBeans()->set($className, $instance);

                // we've to check for post-construct callback
                foreach ($descriptor->getPostConstructCallbacks() as $postConstructCallback) {
                    $instance->$postConstructCallback();
                }

                // return the instance
                return $instance;
            }

            // query whether we've a SLSB
            if ($descriptor instanceof StatelessSessionBeanDescriptorInterface) {
                // if not create a new instance and return it
                $instance = $beanManager->newInstance($className, $sessionId, $args);

                // we've to check for post-construct callback
                foreach ($descriptor->getPostConstructCallbacks() as $postConstructCallback) {
                    $instance->$postConstructCallback();
                }

                // return the instance
                return $instance;
            }

            //  query whether we've a MDB
            if ($descriptor instanceof MessageDrivenBeanDescriptorInterface) {
                // create a new instance and return it
                return $beanManager->newInstance($className, $sessionId, $args);
            }

            // query whether we simply have a bean
            if ($descriptor instanceof BeanDescriptorInterface) {
                // query if the simple bean has to be initialized by a factory
                if ($factory = $descriptor->getFactory()) {
                    // query whether or not the factory is a simple class or a bean
                    if ($className = $factory->getClassName()) {
                        $factoryInstance = $beanManager->newInstance($className, $sessionId);
                    } else {
                        $factoryInstance = $beanManager->getApplication()->search($factory->getName(), array($sessionId));
                    }

                    // create the bean instance by invoking the factory method
                    return call_user_func_array(array($factoryInstance, $factory->getMethod()), $args);
                }

                // create the bean instance without the factory
                return $beanManager->newInstance($descriptor->getClassName(), $sessionId, $args);
            }
        }

        // finally try to let the container create a new instance of the bean
        return $beanManager->newInstance($className, $sessionId, $args);
    }
}
