<?php

/**
 * \AppserverIo\Appserver\Core\Api\DeploymentService
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

namespace AppserverIo\Appserver\Core\Api;

use AppserverIo\Appserver\Core\Utilities\AppEnvironmentHelper;
use AppserverIo\Properties\PropertiesInterface;
use AppserverIo\Configuration\ConfigurationException;
use AppserverIo\Appserver\Core\Api\Node\ContextNode;
use AppserverIo\Appserver\Core\Api\Node\ContainersNode;
use AppserverIo\Appserver\Core\Utilities\SystemPropertyKeys;
use AppserverIo\Appserver\Core\Interfaces\ContainerInterface;
use AppserverIo\Psr\ApplicationServer\Configuration\SystemConfigurationInterface;
use AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface;

/**
 * A service that handles deployment configuration data.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class DeploymentService extends AbstractFileOperationService
{

    /**
     * Returns all deployment configurations.
     *
     * @return array An array with all deployment configurations
     * @see \AppserverIo\Psr\ApplicationServer\ServiceInterface::findAll()
     */
    public function findAll()
    {
        $deploymentNodes = array();
        foreach ($this->getSystemConfiguration()->getContainers() as $container) {
            $deploymentNode = $container->getDeployment();
            $deploymentNodes[$deploymentNode->getUuid()] = $deploymentNode;
        }
        return $deploymentNodes;
    }

    /**
     * Returns the deployment with the passed UUID.
     *
     * @param integer $uuid UUID of the deployment to return
     *
     * @return \AppserverIo\Appserver\Core\Api\Node\\DeploymentNode The deployment with the UUID passed as parameter
     * @see \AppserverIo\Psr\ApplicationServer\ServiceInterface::load()
     */
    public function load($uuid)
    {
        $deploymentNodes = $this->findAll();
        if (array_key_exists($uuid, $deploymentNodes)) {
            return $deploymentNodes[$uuid];
        }
    }

    /**
     * Initializes the context instance for the passed webapp path.
     *
     * @param \AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface $containerNode The container to load the context for
     * @param string                                                                           $webappPath    The path to the web application
     *
     * @return \AppserverIo\Appserver\Core\Api\Node\ContextNode The initialized context instance
     */
    public function loadContextInstance(ContainerConfigurationInterface $containerNode, $webappPath)
    {

        // prepare the context path
        $contextPath = basename($webappPath);

        // load the system properties
        $properties = $this->getSystemProperties($containerNode);

        // append the application specific properties
        $properties->add(SystemPropertyKeys::WEBAPP, $webappPath);
        $properties->add(SystemPropertyKeys::WEBAPP_NAME, $contextPath);

        // validate the base context file
        /** @var \AppserverIo\Appserver\Core\Api\ConfigurationService $configurationService */
        $configurationService = $this->newService('AppserverIo\Appserver\Core\Api\ConfigurationService');
        $configurationService->validateFile($baseContextPath = $this->getConfdDir('context.xml'), null);

        //load it as default if validation succeeds
        $context = new ContextNode();
        $context->initFromFile($baseContextPath);
        $context->replaceProperties($properties);

        // set the context webapp path
        $context->setWebappPath($webappPath);

        // try to load a context configuration (from appserver.xml) for the context path
        if ($contextToMerge = $containerNode->getHost()->getContext($contextPath)) {
            $contextToMerge->replaceProperties($properties);
            $context->merge($contextToMerge);
        }

        // iterate through all context configurations (context.xml), validate and merge them
        foreach ($this->globDir(AppEnvironmentHelper::getEnvironmentAwareGlobPattern($webappPath, 'META-INF/context')) as $contextFile) {
            try {
                // validate the application specific context
                $configurationService->validateFile($contextFile, null);

                // create a new context node instance and replace the properties
                $contextInstance = new ContextNode();
                $contextInstance->initFromFile($contextFile);
                $contextInstance->replaceProperties($properties);

                // merge it into the default configuration
                $context->merge($contextInstance);

            } catch (ConfigurationException $ce) {
                // load the logger and log the XML validation errors
                $systemLogger = $this->getInitialContext()->getSystemLogger();
                $systemLogger->error($ce->__toString());

                // additionally log a message that DS will be missing
                $systemLogger->critical(
                    sprintf('Will skip app specific context file %s, configuration might be faulty.', $contextFile)
                );
            }
        }

        // set the real context name
        $context->setName($contextPath);
        $context->setEnvironmentName(AppEnvironmentHelper::getEnvironmentModifier($webappPath));

        // return the initialized context instance
        return $context;
    }

    /**
     * Initializes the available application contexts and returns them.
     *
     * @param \AppserverIo\Appserver\Core\Interfaces\ContainerInterface $container The container we want to add the applications to
     *
     * @return ContextNode[] The array with the application contexts
     */
    public function loadContextInstancesByContainer(ContainerInterface $container)
    {

        // initialize the array for the context instances
        $contextInstances = array();

        // iterate over all applications and create the context configuration
        foreach (glob($container->getAppBase() . '/*', GLOB_ONLYDIR) as $webappPath) {
            $context = $this->loadContextInstance($container->getContainerNode(), $webappPath);
            $contextInstances[$context->getName()] = $context;
        }

        // return the array with the context instances
        return $contextInstances;
    }

    /**
     * Prepares the system properties for the actual mode.
     *
     * @param \AppserverIo\Properties\PropertiesInterface $properties The properties to prepare
     * @param string                                      $webappPath The path of the web application to prepare the properties with
     *
     * @return void
     */
    protected function prepareSystemProperties(PropertiesInterface $properties, $webappPath)
    {
        // append the application specific properties and replace the properties
        $properties->add(SystemPropertyKeys::WEBAPP, $webappPath);
        $properties->add(SystemPropertyKeys::WEBAPP_NAME, basename($webappPath));
    }

    /**
     * Loads the container instances from the META-INF/containers.xml configuration file of the
     * passed web application path and add/merge them to/with the system configuration.
     *
     * @param \AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface $containerNode       The container node used for property replacement
     * @param \AppserverIo\Psr\ApplicationServer\Configuration\SystemConfigurationInterface    $systemConfiguration The system configuration to add/merge the found containers to/with
     * @param string                                                                           $webappPath          The path to the web application to search for a META-INF/containers.xml file
     *
     * @return void
     */
    public function loadContainerInstance(
        ContainerConfigurationInterface $containerNode,
        SystemConfigurationInterface $systemConfiguration,
        $webappPath
    ) {

        // load the service to validate the files
        /** @var \AppserverIo\Appserver\Core\Api\ConfigurationService $configurationService */
        $configurationService = $this->newService('AppserverIo\Appserver\Core\Api\ConfigurationService');

        // iterate through all server configurations (servers.xml), validate and merge them
        foreach ($this->globDir(AppEnvironmentHelper::getEnvironmentAwareGlobPattern($webappPath, 'META-INF/containers')) as $containersConfigurationFile) {
            try {
                // validate the application specific container configurations
                $configurationService->validateFile($containersConfigurationFile, null);

                // create a new containers node instance
                $containersNodeInstance = new ContainersNode();
                $containersNodeInstance->initFromFile($containersConfigurationFile);

                // load the system properties
                $properties = $this->getSystemProperties($containerNode);

                // prepare the sytsem properties
                $this->prepareSystemProperties($properties, $webappPath);

                /** @var \AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface $containerNodeInstance */
                foreach ($containersNodeInstance->getContainers() as $containerNodeInstance) {
                    // replace the properties for the found container node instance
                    $containerNodeInstance->replaceProperties($properties);
                    // query whether we've to merge or append the server node instance
                    if ($container = $systemConfiguration->getContainer($containerNodeInstance->getName())) {
                        $container->merge($containerNodeInstance);
                    } else {
                        $systemConfiguration->attachContainer($containerNodeInstance);
                    }
                }

            } catch (ConfigurationException $ce) {
                // load the logger and log the XML validation errors
                $systemLogger = $this->getInitialContext()->getSystemLogger();
                $systemLogger->error($ce->__toString());

                // additionally log a message that server configuration will be missing
                $systemLogger->critical(
                    sprintf(
                        'Will skip app specific server configuration because of invalid file %s',
                        $containersConfigurationFile
                    )
                );
            }
        }
    }

    /**
     * Loads the containers, defined by the applications, merges them into
     * the system configuration and returns the merged system configuration.
     *
     * @return \AppserverIo\Psr\ApplicationServer\Configuration\SystemConfigurationInterface The merged system configuration
     */
    public function loadContainerInstances()
    {

        // load the system configuration
        /** @var \AppserverIo\Psr\ApplicationServer\Configuration\SystemConfigurationInterface $systemConfiguration */
        $systemConfiguration = $this->getSystemConfiguration();

        // if applications are NOT allowed to override the system configuration
        if ($systemConfiguration->getAllowApplicationConfiguration() === false) {
            return $systemConfiguration;
        }

        /** @var \AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface $containerNodeInstance */
        foreach ($systemConfiguration->getContainers() as $containerNode) {
            // load the containers application base directory
            $containerAppBase = $this->getBaseDirectory($containerNode->getHost()->getAppBase());

            // iterate over all applications and create the server configuration
            foreach (glob($containerAppBase . '/*', GLOB_ONLYDIR) as $webappPath) {
                $this->loadContainerInstance($containerNode, $systemConfiguration, $webappPath);
            }
        }

        // returns the merged system configuration
        return $systemConfiguration;
    }
}
