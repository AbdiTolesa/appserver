<?php

/**
 * \AppserverIo\Appserver\ServletEngine\ServletManager
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

namespace AppserverIo\Appserver\ServletEngine;

use AppserverIo\Storage\GenericStackable;
use AppserverIo\Storage\StackableStorage;
use AppserverIo\Psr\Application\ApplicationInterface;
use AppserverIo\Appserver\Core\Api\Node\ManagerNodeInterface;
use AppserverIo\Appserver\Core\Interfaces\ManagerFactoryInterface;
use AppserverIo\Appserver\Application\StandardManagerSettings;

/**
 * The servlet manager handles the servlets registered for the application.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class ServletManagerFactory implements ManagerFactoryInterface
{

    /**
     * The main method that creates new instances in a separate context.
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface         $application          The application instance to register the class loader with
     * @param \AppserverIo\Appserver\Core\Api\Node\ManagerNodeInterface $managerConfiguration The manager configuration
     *
     * @return void
     */
    public static function visit(ApplicationInterface $application, ManagerNodeInterface $managerConfiguration)
    {

        // initialize the stackable storage
        $data = new StackableStorage();
        $servlets = new StackableStorage();
        $errorPages = new StackableStorage();
        $initParameters = new StackableStorage();
        $servletMappings = new GenericStackable();
        $securedUrlConfigs = new StackableStorage();
        $sessionParameters = new StackableStorage();

        // initialize the default settings for the stateful session beans
        $servletManagerSettings = new StandardManagerSettings();
        $servletManagerSettings->mergeWithParams($managerConfiguration->getParamsAsArray());

        // initialize the servlet locator
        $servletLocator = new ServletLocator();

        // initialize the servlet manager
        $servletManager = new ServletManager();
        $servletManager->injectData($data);
        $servletManager->injectServlets($servlets);
        $servletManager->injectErrorPages($errorPages);
        $servletManager->injectApplication($application);
        $servletManager->injectInitParameters($initParameters);
        $servletManager->injectResourceLocator($servletLocator);
        $servletManager->injectServletMappings($servletMappings);
        $servletManager->injectSecuredUrlConfigs($securedUrlConfigs);
        $servletManager->injectSessionParameters($sessionParameters);
        $servletManager->injectManagerSettings($servletManagerSettings);
        $servletManager->injectManagerConfiguration($managerConfiguration);

        // create the naming context and add it the manager
        $contextFactory = $managerConfiguration->getContextFactory();
        $contextFactory::visit($servletManager);

        // attach the instance
        $application->addManager($servletManager, $managerConfiguration);
    }
}
