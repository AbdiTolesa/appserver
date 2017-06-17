<?php

/**
 * \AppserverIo\Appserver\DependencyInjectionContainer\Provider
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

namespace AppserverIo\Appserver\DependencyInjectionContainer;

use AppserverIo\Storage\GenericStackable;
use AppserverIo\Lang\Reflection\ClassInterface;
use AppserverIo\Lang\Reflection\ReflectionClass;
use AppserverIo\Lang\Reflection\AnnotationInterface;
use AppserverIo\Psr\Di\ProviderInterface;
use AppserverIo\Psr\Di\ObjectManagerInterface;
use AppserverIo\Psr\Di\DependencyInjectionException;
use AppserverIo\Psr\Servlet\Annotations\Route;
use AppserverIo\Psr\Naming\NamingDirectoryInterface;
use AppserverIo\Psr\Application\ApplicationInterface;
use AppserverIo\Psr\EnterpriseBeans\Annotations\Inject;
use AppserverIo\Psr\EnterpriseBeans\Annotations\MessageDriven;
use AppserverIo\Psr\EnterpriseBeans\Annotations\PreDestroy;
use AppserverIo\Psr\EnterpriseBeans\Annotations\PostConstruct;
use AppserverIo\Psr\EnterpriseBeans\Annotations\PreAttach;
use AppserverIo\Psr\EnterpriseBeans\Annotations\PostDetach;
use AppserverIo\Psr\EnterpriseBeans\Annotations\Singleton;
use AppserverIo\Psr\EnterpriseBeans\Annotations\Startup;
use AppserverIo\Psr\EnterpriseBeans\Annotations\Stateful;
use AppserverIo\Psr\EnterpriseBeans\Annotations\Stateless;
use AppserverIo\Psr\EnterpriseBeans\Annotations\Schedule;
use AppserverIo\Psr\EnterpriseBeans\Annotations\Timeout;
use AppserverIo\Psr\EnterpriseBeans\Annotations\EnterpriseBean;
use AppserverIo\Psr\EnterpriseBeans\Annotations\Resource;
use AppserverIo\Psr\EnterpriseBeans\Annotations\PersistenceUnit;

/**
 * A basic dependency injection provider implementation.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 *
 * @property \AppserverIo\Psr\Naming\NamingDirectoryInterface $namingDirectory The applications naming directory interface
 * @property \AppserverIo\Psr\Application\ApplicationInterfac $application     The application instance
 */
class Provider extends GenericStackable implements ProviderInterface
{

    /**
     * Dependencies for the actual injection target.
     *
     * @var array
     */
    protected $dependencies = array();

    /**
     * The managers unique identifier.
     *
     * @return string The unique identifier
     * @see \AppserverIo\Psr\Application\ManagerInterface::getIdentifier()
     */
    public function getIdentifier()
    {
        return ProviderInterface::IDENTIFIER;
    }

    /**
     * Has been automatically invoked by the container after the application
     * instance has been created.
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     * @see \AppserverIo\Psr\Application\ManagerInterface::initialize()
     */
    public function initialize(ApplicationInterface $application)
    {
        // do nothing here
    }

    /**
     * Returns the value with the passed name from the context.
     *
     * @param string $key The key of the value to return from the context.
     *
     * @return mixed The requested attribute
     */
    public function getAttribute($key)
    {
        // not yet implemented
    }

    /**
     * Injects the storage for the loaded reflection classes.
     *
     * @param \AppserverIo\Storage\GenericStackable $reflectionClasses The storage for the reflection classes
     *
     * @return void
     */
    public function injectReflectionClasses($reflectionClasses)
    {
        $this->reflectionClasses = $reflectionClasses;
    }

    /**
     * Injects the naming directory aliases.
     *
     * @param \AppserverIo\Storage\GenericStackable $namingDirectoryAliases The naming directory aliases
     *
     * @return void
     */
    public function injectNamingDirectoryAliases($namingDirectoryAliases)
    {
        $this->namingDirectoryAliases = $namingDirectoryAliases;
    }

    /**
     * The application instance.
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface $application The application instance
     *
     * @return void
     */
    public function injectApplication(ApplicationInterface $application)
    {
        $this->application = $application;
    }

    /**
     * Returns the naming context instance.
     *
     * @return \AppserverIo\Psr\Naming\InitialContext The naming context instance
     */
    public function getInitialContext()
    {
        throw new \Exception(sprintf('%s not implemented yet', __METHOD__));
    }

    /**
     * Returns the application instance.
     *
     * @return \AppserverIo\Psr\Application\ApplicationInterface The application instance
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * Returns the applications naming directory.
     *
     * @return \AppserverIo\Psr\Naming\NamingDirectoryInterface The applications naming directory interface
     */
    public function getNamingDirectory()
    {
        return $this->getApplication()->getNamingDirectory();
    }

    /**
     * Creates a new new instance of the annotation type, defined in the passed reflection annotation.
     *
     * @param \AppserverIo\Lang\Reflection\AnnotationInterface $annotation The reflection annotation we want to create the instance for
     *
     * @return \AppserverIo\Lang\Reflection\AnnotationInterface The real annotation instance
     */
    public function newAnnotationInstance(AnnotationInterface $annotation)
    {
        return $annotation->newInstance($annotation->getAnnotationName(), $annotation->getValues());
    }

    /**
     * Returns a reflection class instance for the passed class name.
     *
     * @param string $className The class name to return the reflection instance for
     *
     * @return \AppserverIo\Lang\Reflection\ReflectionClass The reflection instance
     */
    public function newReflectionClass($className)
    {

        // initialize the array with the annotations we want to ignore
        $annotationsToIgnore = array(
            'author',
            'package',
            'license',
            'copyright',
            'param',
            'return',
            'throws',
            'see',
            'link'
        );

        // initialize the array with the aliases for the enterprise bean annotations
        $annotationAliases = array(
            Route::ANNOTATION           => Route::__getClass(),
            Inject::ANNOTATION          => Inject::__getClass(),
            Resource::ANNOTATION        => Resource::__getClass(),
            Timeout::ANNOTATION         => Timeout::__getClass(),
            Stateless::ANNOTATION       => Stateless::__getClass(),
            Stateful::ANNOTATION        => Stateful::__getClass(),
            Startup::ANNOTATION         => Startup::__getClass(),
            Singleton::ANNOTATION       => Singleton::__getClass(),
            Schedule::ANNOTATION        => Schedule::__getClass(),
            PreAttach::ANNOTATION       => PreAttach::__getClass(),
            PostDetach::ANNOTATION      => PostDetach::__getClass(),
            PreDestroy::ANNOTATION      => PreDestroy::__getClass(),
            PostConstruct::ANNOTATION   => PostConstruct::__getClass(),
            MessageDriven::ANNOTATION   => MessageDriven::__getClass(),
            EnterpriseBean::ANNOTATION  => EnterpriseBean::__getClass(),
            PersistenceUnit::ANNOTATION => PersistenceUnit::__getClass()
        );

        // return the reflection class instance
        return new ReflectionClass($className, $annotationsToIgnore, $annotationAliases);
    }

    /**
     * Returns a new reflection class intance for the passed class name.
     *
     * @param string $className The class name to return the reflection class instance for
     *
     * @return \AppserverIo\Lang\Reflection\ReflectionClass The reflection instance
     */
    public function getReflectionClass($className)
    {

        // check if we've already initialized the reflection class
        if (isset($this->reflectionClasses[$className]) === false) {
            $this->reflectionClasses[$className] = $this->newReflectionClass($className);
        }

        // return the reflection class instance
        return $this->reflectionClasses[$className];
    }

    /**
     * Returns a reflection class intance for the passed class name.
     *
     * @param object $instance The instance to return the reflection class instance for
     *
     * @return \AppserverIo\Lang\Reflection\ReflectionClass The reflection instance
     * @see \AppserverIo\Psr\Di\ProviderInterface::newReflectionClass()
     * @see \AppserverIo\Psr\Di\ProviderInterface::getReflectionClass()
     */
    public function getReflectionClassForObject($instance)
    {
        return $this->getReflectionClass(get_class($instance));
    }

    /**
     * Adds the passe reflection class instance to the DI provider.
     *
     * @param \AppserverIo\Lang\Reflection\ClassInterface $reflectionClass The reflection class instance to add
     *
     * @return void
     */
    public function setReflectionClass(ClassInterface $reflectionClass)
    {
        $this->reflectionClasses[$reflectionClass->getName()] = $reflectionClass;
    }

    /**
     * Loads the dependencies for the passed class name.
     *
     * @param string $className The class name to load the dependencies for
     *
     * @throws DependencyInjectionException
     * @return array The
     */
    protected function loadDependencies($className)
    {

        // query whether or not the dependencies have been loaded
        if (isset($this->dependencies[$className])) {
            return $this->dependencies[$className];
        }

        // initialize the array with the dependencies
        $dependencies = array('method' =>  array(),  'property' => array(), 'constructor' => array());

        // load the object manager instance
        /** @var \AppserverIo\Psr\Di\ObjectManagerInterface $objectManager */
        $objectManager = $this->getNamingDirectory()->search(
            sprintf('php:global/%s/%s', $this->getApplication()->getUniqueName(), ObjectManagerInterface::IDENTIFIER)
        );

        // load the object descriptor for the instance from the the object manager
        if ($objectManager->hasObjectDescriptor($className)) {
            // load the object descriptor
            $objectDescriptor = $objectManager->getObjectDescriptor($className);

            // check if a reflection class instance has been passed or is already available
            $reflectionClass = $this->getReflectionClass($className);

            // check for declared EPB and resource references
            foreach ($objectDescriptor->getReferences() as $reference) {
                // check if we've a reflection target defined
                if ($injectionTarget = $reference->getInjectionTarget()) {
                    // load the instance to inject by lookup the initial context
                    $toInject = $this->getNamingDirectory()->search(
                        sprintf('php:global/%s/%s', $this->getApplication()->getUniqueName(), $reference->getName())
                    );

                    // add the dependency to the array
                    if ($targetName = $injectionTarget->getTargetMethod()) {
                        $dependencies['method'][$targetName] = $toInject;
                    } elseif ($targetName = $injectionTarget->getTargetProperty()) {
                        $dependencies['property'][$targetName] = $toInject;
                    } else {
                        // throw an exception
                        throw new DependencyInjectionException(
                            sprintf('Can\'t find property or method %s in class %s', $targetName, $className)
                        );
                    }
                }
            }

        } else {
            // check if a reflection class instance has been passed or is already available
            $reflectionClass = $this->getReflectionClass($className);
            // query whether or not, we've a constructor
            if ($reflectionClass->hasMethod('__construct')) {
                // load the reflection method for the constructor
                $reflectionMethod = $reflectionClass->getMethod('__construct');
                // iterate over the constructor parameters
                /** @var \AppserverIo\Lang\Reflection\ParameterInterface $reflectionParameter */
                foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                    $dependencies['constructor'][] = $this->get($reflectionParameter->getType());
                }
            }
        }

        // return the array with the loaded dependencies
        return $this->dependencies[$className] = $dependencies;
    }

    /**
     * Injects the dependencies of the passed instance.
     *
     * @param object $instance The instance to inject the dependencies for
     *
     * @return void
     */
    public function injectDependencies($instance)
    {

        // load the reflection class
        $reflectionClass = $this->getReflectionClassForObject($instance);

        // load the dependencies for the passed instance
        $dependencies = $this->loadDependencies($reflectionClass->getName());

        // inject dependencies by method
        foreach ($dependencies['method'] as $methodName => $toInject) {
            // query whether or not the method exists
            if ($reflectionClass->hasMethod($methodName)) {
                // inject the target by invoking the method
                $instance->$methodName($toInject);
            }
        }

        // inject dependencies by property
        foreach ($dependencies['property'] as $propertyName => $toInject) {
            // query whether or not the property exists
            if ($reflectionClass->hasProperty($propertyName)) {
                // load the reflection property
                $reflectionProperty = $reflectionClass->getProperty($propertyName);

                // load the PHP ReflectionProperty instance to inject the bean instance
                $phpReflectionProperty = $reflectionProperty->toPhpReflectionProperty();
                $phpReflectionProperty->setAccessible(true);
                $phpReflectionProperty->setValue($instance, $toInject);
            }
        }
    }

    /**
     * Returns a new instance of the passed class name.
     *
     * @param string $className The fully qualified class name to return the instance for
     *
     * @return object The instance itself
     * @deprecated Since 1.1.5 Use \Psr\Container\ContainerInterface::get() instead
     */
    public function newInstance($className)
    {
        return $this->get($className);
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for
     *
     * @throws \Psr\Container\NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws \Psr\Container\ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get($id)
    {

        // load/create and return a new instance
        $reflectionClass = $this->getReflectionClass($id);

        // load the dependencies for the passed class
        $dependencies = $this->loadDependencies($reflectionClass->getName());

        // check if we've a constructor
        if ($reflectionClass->hasMethod('__construct')) {
            $instance = $reflectionClass->newInstanceArgs($dependencies['constructor']);
        } else {
            $instance = $reflectionClass->newInstance();
        }

        // inject the property/method dependencies
        $this->injectDependencies($instance);

        // finally return the instance
        return $instance;
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning TRUE does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return boolean TRUE if an entroy for the given identifier exists, else FALSE
     */
    public function has($id)
    {
        return class_exists($id, true);
    }
}
