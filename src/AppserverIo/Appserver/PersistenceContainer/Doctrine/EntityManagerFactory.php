<?php

/**
 * \AppserverIo\Appserver\PersistenceContainer\Doctrine\EntityManagerFactory
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

namespace AppserverIo\Appserver\PersistenceContainer\Doctrine;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use AppserverIo\Psr\Application\ApplicationInterface;
use AppserverIo\Appserver\Doctrine\Utils\ConnectionUtil;
use AppserverIo\Description\Configuration\PersistenceUnitConfigurationInterface;
use AppserverIo\Description\Configuration\MetadataConfigurationInterface;

/**
 * Factory implementation for a Doctrin EntityManager instance.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class EntityManagerFactory
{

    /**
     * The application instance.
     *
     * @var \AppserverIo\Psr\Application\ApplicationInterface
     */
    protected $application;

    /**
     *  @var \AppserverIo\Description\Configuration\PersistenceUnitConfigurationInterface
     */
    protected $persistenceUnitNode;

    /**
     * Mapping metadata type to factory method.
     *
     * @var array
     */
    protected static $metadataMapping = array(
        'xml' => 'createXMLMetadataConfiguration',
        'yaml' => 'createYAMLMetadataConfiguration',
        'annotation' => 'createAnnotationMetadataConfiguration'
    );

    /**
     *
     * @param \AppserverIo\Psr\Application\ApplicationInterface                            $application         The application instance
     * @param \AppserverIo\Description\Configuration\PersistenceUnitConfigurationInterface $persistenceUnitNode The persistence unit configuration node
     */
    public function __construct(
        ApplicationInterface $application,
        PersistenceUnitConfigurationInterface $persistenceUnitNode
    ) {
        $this->application = application;
        $this->persistenceUnitNode = $persistenceUnitNode;
    }

    /**
     * Creates a new entity manager instance based on the passed configuration.
     *
     * @return object The entity manager instance
     */
    public function factory()
    {

        // register additional annotation libraries
        foreach ($this->persistenceUnitNode->getAnnotationRegistries() as $annotationRegistry) {
            AnnotationRegistry::registerAutoloadNamespace(
                $annotationRegistry->getNamespace(),
                $annotationRegistry->getDirectoriesAsArray($this->application->getWebappPath())
            );
        }

        // globally ignore configured annotations to ignore
        foreach ($this->persistenceUnitNode->getIgnoredAnnotations() as $ignoredAnnotation) {
            AnnotationReader::addGlobalIgnoredName($ignoredAnnotation->getNodeValue()->__toString());
        }

        // load the metadata configuration
        $metadataConfiguration = $this->persistenceUnitNode->getMetadataConfiguration();

        // prepare the setup properties
        $absolutePaths = $metadataConfiguration->getDirectoriesAsArray($this->application->getWebappPath());
        $proxyDir = $metadataConfiguration->getParam(MetadataConfigurationInterface::PARAM_PROXY_DIR);
        $isDevMode = $metadataConfiguration->getParam(MetadataConfigurationInterface::PARAM_IS_DEV_MODE);
        $useSimpleAnnotationReader = $metadataConfiguration->getParam(MetadataConfigurationInterface::PARAM_USE_SIMPLE_ANNOTATION_READER);

        // load the factory method from the available mappings
        $factoryMethod = EntityManagerFactory::$metadataMapping[$metadataConfiguration->getType()];

        // create the database configuration and initialize the entity manager
        $configuration = Setup::$factoryMethod($absolutePaths, $isDevMode, $proxyDir, null, $useSimpleAnnotationReader);

        // load the datasource node
        $datasourceNode = null;
        foreach ($this->application->getInitialContext()->getSystemConfiguration()->getDatasources() as $datasourceNode) {
            if ($datasourceNode->getName() === $this->persistenceUnitNode->getDatasource()->getName()) {
                break;
            }
        }

        // throw a exception if the configured datasource is NOT available
        if ($datasourceNode == null) {
            throw new \Exception(
                sprintf(
                    'Can\'t find a datasource node for persistence unit %s',
                    $this->persistenceUnitNode->getName()
                )
            );
        }

        // load the database node
        $databaseNode = $datasourceNode->getDatabase();

        // throw an exception if the configured database is NOT available
        if ($databaseNode == null) {
            throw new \Exception(
                sprintf(
                    'Can\'t find database node for persistence unit %s',
                    $this->persistenceUnitNode->getName()
                )
            );
        }

        // load the driver node
        $driverNode = $databaseNode->getDriver();

        // throw an exception if the configured driver is NOT available
        if ($driverNode == null) {
            throw new \Exception(
                sprintf(
                    'Can\'t find driver node for persistence unit %s',
                    $this->persistenceUnitNode->getName()
                )
            );
        }

        // initialize and return a entity manager decorator instance
        return new DoctrineEntityManagerDecorator(
            EntityManager::create(ConnectionUtil::get($this->application)->fromDatabaseNode($databaseNode), $configuration)
        );
    }
}
