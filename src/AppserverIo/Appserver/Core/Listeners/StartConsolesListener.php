<?php

/**
 * AppserverIo\Appserver\Core\Listeners\StartConsolesListener
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

namespace AppserverIo\Appserver\Core\Listeners;

use League\Event\EventInterface;
use AppserverIo\Psr\ApplicationServer\ApplicationServerInterface;

/**
 * Listener that initializes and binds the consoles found in the system configuration.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class StartConsolesListener extends AbstractSystemListener
{

    /**
     * Handle an event.
     *
     * @param \League\Event\EventInterface $event The triggering event
     *
     * @return void
     * @see \League\Event\ListenerInterface::handle()
     */
    public function handle(EventInterface $event)
    {

        try {
            // load the application server instance
            /** @var \AppserverIo\Psr\ApplicationServer\ApplicationServerInterface $applicationServer */
            $applicationServer = $this->getApplicationServer();

            // write a log message that the event has been invoked
            $applicationServer->getSystemLogger()->info($event->getName());

            // and initialize a console for each node found in the configuration
            /** @var \AppserverIo\Psr\Cli\Configuration\ConsoleConfigurationInterface $consoleNode */
            foreach ($applicationServer->getSystemConfiguration()->getConsoles() as $consoleNode) {
                /** @var \AppserverIo\Appserver\Core\Interfaces\ConsoleFactoryInterface $consoleFactory */
                $consoleFactory = $consoleNode->getFactory();

                // use the factory if available
                /** @var \AppserverIo\Appserver\Core\Interfaces\ConsoleInterface $console */
                $console = $consoleFactory::factory($applicationServer, $consoleNode);

                // register the console as service
                $applicationServer->bindService(ApplicationServerInterface::ADMINISTRATION, $console);
            }

        } catch (\Exception $e) {
            $applicationServer->getSystemLogger()->error($e->__toString());
        }
    }
}
