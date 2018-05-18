<?php

/**
 * \AppserverIo\Appserver\Core\Api\ContainerService
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

use AppserverIo\Appserver\Core\Utilities\FileSystem;
use AppserverIo\Appserver\Meta\Composer\Script\Setup;
use AppserverIo\Appserver\Meta\Composer\Script\SetupKeys;
use AppserverIo\Appserver\Core\Utilities\DirectoryKeys;
use AppserverIo\Appserver\Core\Utilities\FileKeys;

/**
 * A service that handles container configuration data.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
class ContainerService extends AbstractFileOperationService
{

    /**
     * The flag that shows that the application server has been installed.
     *
     * @var string
     */
    const FLAG_IS_INSTALLED = '.is-installed';

    /**
     * The available setup modes.
     *
     * @var string
     */
    const SETUP_MODE_PROD = 'prod';
    const SETUP_MODE_DEV = 'dev';
    const SETUP_MODE_INSTALL = 'install';

    /**
     * Creates the SSL file passed as parameter or nothing if the file already exists.
     *
     * @param \SplFileInfo $certificate The file info about the SSL file to generate
     *
     * @return void
     *
     * @throws \Exception
     */
    public function createSslCertificate(\SplFileInfo $certificate)
    {

        // first we've to check if OpenSSL is available
        if (!$this->isOpenSslAvailable()) {
            return;
        }

        // do nothing if the file is already available
        if ($certificate->isFile()) {
            return;
        }

        // prepare the certificate data from our configuration
        $dn = array(
            "countryName" => "DE",
            "stateOrProvinceName" => "Bavaria",
            "localityName" => "Kolbermoor",
            "organizationName" => "appserver.io",
            "organizationalUnitName" => "Development",
            "commonName" => gethostname(),
            "emailAddress" => "info@appserver.io"
        );

        // check the operating system
        switch ($this->getOsIdentifier()) {

            case 'DAR': // on Mac OS X use the system default configuration

                $configargs = array('config' => $this->getBaseDirectory('/ssl/openssl.cnf'));
                break;

            case 'WIN': // on Windows use the system configuration we deliver

                $configargs = array('config' => $this->getBaseDirectory('/php/extras/ssl/openssl.cnf'));
                break;

            default: // on all other use a standard configuration

                $configargs = array(
                    'digest_alg' => 'sha256',
                    'x509_extensions' => 'v3_ca',
                    'req_extensions'   => 'v3_req',
                    'private_key_bits' => 2048,
                    'private_key_type' => OPENSSL_KEYTYPE_RSA,
                    'encrypt_key' => false
                );
        }

        // generate a new private (and public) key pair
        $privkey = openssl_pkey_new($configargs);

        // Generate a certificate signing request
        $csr = openssl_csr_new($dn, $privkey, $configargs);

        // create a self-signed cert that is valid for 365 days
        $sscert = openssl_csr_sign($csr, null, $privkey, 365, $configargs);

        // export the cert + pk files
        $certout = '';
        $pkeyout = '';
        openssl_x509_export($sscert, $certout);
        openssl_pkey_export($privkey, $pkeyout, null, $configargs);

        // write the SSL certificate data to the target
        $file = $certificate->openFile('w');
        if (($written = $file->fwrite($certout . $pkeyout)) === false) {
            throw new \Exception(sprintf('Can\'t create SSL certificate %s', $certificate->getPathname()));
        }

        // log a message that the file has been written successfully
        $this->getInitialContext()->getSystemLogger()->info(
            sprintf('Successfully created %s with %d bytes', $certificate->getPathname(), $written)
        );

        // log any errors that occurred here
        while (($e = openssl_error_string()) !== false) {
            $this->getInitialContext()->getSystemLogger()->debug($e);
        }
    }

    /**
     * Return's all container node configurations.
     *
     * @return array An array with container node configurations
     * @see \AppserverIo\Psr\ApplicationServer\ServiceInterface::findAll()
     */
    public function findAll()
    {
        return $this->getSystemConfiguration()->getContainers();
    }

    /**
     * Returns the application base directory for the container
     * with the passed UUID.
     *
     * @param string $uuid UUID of the container to return the application base directory for
     *
     * @return string The application base directory for this container
     */
    public function getAppBase($uuid)
    {
        return $this->load($uuid)->getHost()->getAppBase();
    }

    /**
     * Returns true if the OpenSSL extension is loaded, false otherwise
     *
     * @return boolean
     *
     * @codeCoverageIgnore this will most likely always be mocked/stubbed, and it is trivial anyway
     */
    protected function isOpenSslAvailable()
    {
        return extension_loaded('openssl');
    }

    /**
     * Returns the container for the passed UUID.
     *
     * @param string $uuid Unique UUID of the container to return
     *
     * @return \AppserverIo\Appserver\Core\Api\Node\ContainerNode The container with the UUID passed as parameter
     * @see \AppserverIo\Psr\ApplicationServer\ServiceInterface::load($uuid)
     */
    public function load($uuid)
    {
        $containers = $this->findAll();
        if (array_key_exists($uuid, $containers)) {
            return $containers[$uuid];
        }
    }

    /**
     * Prepares filesystem to be sure that everything is on place as expected
     *
     * @return void
     * @throws \Exception Is thrown if a server directory can't be created
     */
    public function prepareFileSystem()
    {

        // load the directories
        $directories = $this->getDirectories();

        // load user and group from system configuration
        $user = $this->getSystemConfiguration()->getUser();
        $group = $this->getSystemConfiguration()->getGroup();

        // check if the necessary directories already exists, if not, create them
        foreach (DirectoryKeys::getServerDirectoryKeysToBeCreated() as $directoryKey) {
            // prepare the path to the directory to be created
            $toBeCreated = $this->realpath($directories[$directoryKey]);

            // prepare the directory name and check if the directory already exists
            if (is_dir($toBeCreated) === false) {
                // if not, try to create it
                if (mkdir($toBeCreated, 0755, true) === false) {
                    throw new \Exception(
                        sprintf('Can\'t create necessary directory %s while starting application server', $toBeCreated)
                    );
                }

                // set user/group specified from the system configuration
                if ($this->setUserRights(new \SplFileInfo($toBeCreated), $user, $group) === false) {
                    throw new \Exception(
                        sprintf('Can\'t switch user/group to %s/% for directory %s while starting application server', $user, $group, $toBeCreated)
                    );
                }
            }
        }

        // create the container specific directories
        /** @var \AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface $containerNode */
        foreach ($this->getSystemConfiguration()->getContainers() as $containerNode) {
            // iterate over the container host's directories
            foreach ($containerNode->getHost()->getDirectories() as $directory) {
                // prepare the path to the directory to be created
                $toBeCreated = $this->realpath($directory);

                // prepare the directory name and check if the directory already exists
                if (is_dir($toBeCreated) === false) {
                    // if not, try to create it
                    if (mkdir($toBeCreated, 0755, true) === false) {
                        throw new \Exception(
                            sprintf('Can\'t create necessary directory %s while starting application server', $toBeCreated)
                        );
                    }

                    // set user/group specified from the system configuration
                    if ($this->setUserRights(new \SplFileInfo($toBeCreated), $user, $group) === false) {
                        throw new \Exception(
                            sprintf('Can\'t switch user/group to %s/% for directory %s while starting application server', $user, $group, $toBeCreated)
                        );
                    }
                }
            }
        }

        // check if specific directories has to be cleaned up on startup
        foreach (DirectoryKeys::getServerDirectoryKeysToBeCleanedUp() as $directoryKey) {
            // prepare the path to the directory to be cleaned up
            $toBeCleanedUp = $this->realpath($directories[$directoryKey]);

            // if the directory exists, clean it up
            if (is_dir($toBeCleanedUp)) {
                $this->cleanUpDir(new \SplFileInfo($toBeCleanedUp));
            }
        }

        // check if needed files do exist and have the correct user rights
        $files = $this->getFiles();
        foreach (FileKeys::getServerFileKeysToBeCreated() as $fileKeys) {
            // prepare the path to the file to be created
            $toBeCreated = $this->realpath($files[$fileKeys]);

            // touch the file (will lead to its creation if it does not exist by now)
            if (touch($toBeCreated) === false) {
                throw new \Exception(
                    sprintf('Can\'t create necessary file %s while starting application server', $toBeCreated)
                );
            } else {
                chmod($toBeCreated, 0755);
            }

            // set user/group specified from the system configuration
            if ($this->setUserRight(new \SplFileInfo($toBeCreated), $user, $group) === false) {
                throw new \Exception(
                    sprintf('Can\'t switch user/group to %s/% for file %s while starting application server', $user, $group, $toBeCreated)
                );
            }
        }
    }

    /**
     * Return's the install flag information from the configuration directory.
     *
     * @return \SplFileInfo The install flag information
     */
    public function getIsInstalledFlag()
    {
        return new \SplFileInfo($this->getConfdDir(ContainerService::FLAG_IS_INSTALLED));
    }

    /**
     * Switches the setup mode to the passed value.
     *
     * @param string $newMode               The mode to switch to
     * @param string $configurationFilename The path of the configuration filename
     * @param string $user                  The name of the user who started the application server
     *
     * @return void
     * @throws \Exception Is thrown for an invalid setup mode passed
     */
    public function switchSetupMode($newMode, $configurationFilename, $user)
    {

        // log a message that we switch setup mode now
        $this->getInitialContext()->getSystemLogger()->info(sprintf('Now switch mode to %s!!!', $newMode));

        // init setup context
        Setup::prepareContext($this->getBaseDirectory());

        // init variable for the group
        $group = null;

        // pattern to replace the user in the etc/appserver/appserver.xml/appserver-single-app.xml file
        $configurationUserReplacePattern = '/(<appserver[^>]+>[^<]+<params>.*<param name="user[^>]+>)([^<]+)/s';

        // check setup modes
        switch ($newMode) {

            // prepares everything for developer mode
            case ContainerService::SETUP_MODE_DEV:
                // get defined group from configuration
                $group = Setup::getValue(SetupKeys::GROUP);
                // replace user in configuration file
                file_put_contents(
                    $configurationFilename,
                    preg_replace(
                        $configurationUserReplacePattern,
                        '${1}' . $user,
                        file_get_contents($configurationFilename)
                    )
                );
                // replace the user in the PHP-FPM configuration file
                file_put_contents(
                    $this->getEtcDir('php-fpm.conf'),
                    preg_replace(
                        '/user = (.*)/',
                        'user = ' . $user,
                        file_get_contents($this->getEtcDir('php-fpm.conf'))
                    )
                );
                // add everyone write access to configuration files for dev mode
                FileSystem::recursiveChmod($this->getEtcDir(), 0777, 0777);

                break;

            // prepares everything for production mode
            case ContainerService::SETUP_MODE_PROD:
                // get defined user and group from configuration
                $user = Setup::getValue(SetupKeys::USER);
                $group = Setup::getValue(SetupKeys::GROUP);
                // replace user to be same as user in configuration file
                file_put_contents(
                    $configurationFilename,
                    preg_replace(
                        $configurationUserReplacePattern,
                        '${1}' . $user,
                        file_get_contents($configurationFilename)
                    )
                );
                // replace the user in the PHP-FPM configuration file
                file_put_contents(
                    $this->getEtcDir('php-fpm.conf'),
                    preg_replace(
                        '/user = (.*)/',
                        'user = ' . $user,
                        file_get_contents($this->getEtcDir('php-fpm.conf'))
                    )
                );

                // set correct file permissions for configurations
                FileSystem::recursiveChmod($this->getEtcDir());

                break;

            // prepares everything for first installation which is default mode
            case ContainerService::SETUP_MODE_INSTALL:
                // load the flag marked the server as installed
                $isInstalledFlag = $this->getIsInstalledFlag();

                // first check if it is a fresh installation
                if ($isInstalledFlag->isReadable() === false) {
                     // first iterate over all containers deploy directories and look for application archives
                     /** @var \AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface $containerNode */
                    foreach ($this->getSystemConfiguration()->getContainers() as $containerNode) {
                        // iterate over all found application archives and create the .dodeploy flag file
                        foreach (glob($this->getDeployDir($containerNode, '/*.phar')) as $archive) {
                            touch(sprintf('%s.dodeploy', $archive));
                        }
                    }
                }

                // create is installed flag for prevent further setup install mode calls
                touch($isInstalledFlag);

                // get defined user and group from configuration
                $user = Setup::getValue(SetupKeys::USER);
                $group = Setup::getValue(SetupKeys::GROUP);

                // set correct file permissions for configurations
                FileSystem::recursiveChmod($this->getEtcDir());

                break;

            default:
                throw new \Exception(sprintf('Invalid setup mode %s given', $newMode));
        }

        // check if user and group is set
        if (!is_null($user) && !is_null($group)) {
            // get needed files as accessable for all root files remove "." and ".." from the list
            $rootFiles = scandir($this->getBaseDirectory());
            // iterate all files
            foreach ($rootFiles as $rootFile) {
                // we want just files on root dir
                if (is_file($rootFile) && !in_array($rootFile, array('.', '..'))) {
                    FileSystem::chmod($rootFile, 0644);
                    FileSystem::chown($rootFile, $user, $group);
                }
            }

            // ... and change own and mod of following directories
            FileSystem::chown($this->getBaseDirectory(), $user, $group);
            FileSystem::recursiveChown($this->getBaseDirectory('resources'), $user, $group);
            FileSystem::recursiveChmod($this->getBaseDirectory('resources'));
            FileSystem::recursiveChown($this->getBaseDirectory('src'), $user, $group);
            FileSystem::recursiveChmod($this->getBaseDirectory('src'));
            FileSystem::recursiveChown($this->getBaseDirectory('var'), $user, $group);
            FileSystem::recursiveChmod($this->getBaseDirectory('var'));
            FileSystem::recursiveChown($this->getBaseDirectory('tests'), $user, $group);
            FileSystem::recursiveChmod($this->getBaseDirectory('tests'));
            FileSystem::recursiveChown($this->getBaseDirectory('vendor'), $user, $group);
            FileSystem::recursiveChmod($this->getBaseDirectory('vendor'));

            // ... and the change own and mod for the system's temporary directory
            FileSystem::recursiveChown($this->getSystemTmpDir(), $user, $group);
            FileSystem::recursiveChmod($this->getSystemTmpDir());

            // ... and change own and mod for the container specific directories
            /** @var \AppserverIo\Psr\ApplicationServer\Configuration\ContainerConfigurationInterface $containerNode */
            foreach ($this->getSystemConfiguration()->getContainers() as $containerNode) {
                FileSystem::recursiveChown($this->getWebappsDir($containerNode), $user, $group);
                FileSystem::recursiveChmod($this->getWebappsDir($containerNode));
                FileSystem::recursiveChown($this->getTmpDir($containerNode), $user, $group);
                FileSystem::recursiveChmod($this->getTmpDir($containerNode));
                FileSystem::recursiveChown($this->getDeployDir($containerNode), $user, $group);
                FileSystem::recursiveChmod($this->getDeployDir($containerNode));
            }

            // make server.php executable
            FileSystem::chmod($this->getBaseDirectory('server.php'), 0755);

            // log a message that we successfully switched to the new setup mode
            $this->getInitialContext()->getSystemLogger()->info(sprintf("Setup for mode '%s' done successfully!", $newMode));

        } else {
            throw new \Exception('No user or group given');
        }
    }
}
