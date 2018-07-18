<?php

/**
 * \AppserverIo\Appserver\Core\Api\Node\JobsNodeTrait
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
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Appserver\Core\Api\Node;

use AppserverIo\Description\Annotations as DI;

/**
 * Abstract node that serves nodes having a jobs/job child.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/appserver
 * @link      http://www.appserver.io
 */
trait JobsNodeTrait
{

    /**
     * The jobs.
     *
     * @var array
     * @DI\Mapping(nodeName="jobs/job", nodeType="array", elementType="AppserverIo\Appserver\Core\Api\Node\JobNode")
     */
    protected $jobs = array();

    /**
     * Array with the jobs to set.
     *
     * @param array $jobs The jobs to set
     *
     * @return void
     */
    public function setJobs($jobs)
    {
        $this->jobs = $jobs;
    }

    /**
     * Array with the jobs.
     *
     * @return array
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * Returns the job with the passed name.
     *
     * @param string $name The name of the job to be returned
     *
     * @return mixed The requested job
     */
    public function getJob($name)
    {
        foreach ($this->getJobs() as $job) {
            if ($job->getName() === $name) {
                return $job;
            }
        }
    }
}
