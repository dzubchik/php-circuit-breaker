<?php
declare(strict_types = 1);


namespace Paymaxi\Component\CircuitBreaker\Core;

use Paymaxi\Component\CircuitBreaker\CircuitBreakerInterface;
use Paymaxi\Component\CircuitBreaker\Storage\StorageInterface;

/**
 * Allows user code to track availability of any service by serviceName.
 *
 * @see \Paymaxi\Component\CircuitBreaker\CircuitBreakerInterface
 * @package Paymaxi\Component\CircuitBreaker\Components
 */
class CircuitBreaker implements CircuitBreakerInterface
{

    /**
     * @var CircuitBreakerInterface used to load/save availability statistics
     */
    protected $storageAdapter;

    /**
     * @var int default threshold, if service fails this many times will be disabled
     */
    protected $defaultMaxFailures;

    /**
     * @var int  how many seconds should we wait before retry
     */
    protected $defaultRetryTimeout;

    /**
     * Array with configuration per service name, format:
     *  array(
     *      "serviceName1" => array('maxFailures' => X, 'retryTimeout => Y),
     *      "serviceName2" => array('maxFailures' => X, 'retryTimeout => Y),
     *  )
     *
     * @var array[] settings per service name
     */
    protected $settings = array();

    /**
     * Configure instance with storage implementation and default threshold and retry timeout.
     *
     * @param StorageInterface $storage storage implementation
     * @param int $maxFailures default threshold, if service fails this many times will be disabled
     * @param int $retryTimeout how many seconds should we wait before retry
     */
    public function __construct(StorageInterface $storage, $maxFailures = 20, $retryTimeout = 60)
    {
        $this->storageAdapter = $storage;
        $this->defaultMaxFailures = $maxFailures;
        $this->defaultRetryTimeout = $retryTimeout;
    }

    /**
     * Use this method only if you want to add server specific threshold and retry timeout.
     *
     * @param $serviceName
     * @param int $maxFailures default threshold, if service fails this many times will be disabled
     * @param int $retryTimeout how many seconds should we wait before retry
     *
     * @return CircuitBreaker
     * @internal param StorageInterface $storage storage implementation
     */
    public function setServiceSettings($serviceName, $maxFailures, $retryTimeout)
    {
        $this->settings[$serviceName] = array(
            'maxFailures'  => $maxFailures ? $maxFailures : $this->defaultMaxFailures,
            'retryTimeout' => $retryTimeout ? $retryTimeout : $this->defaultRetryTimeout,
        );

        return $this;
    }

    // ---------------------- HELPERS -----------------

    public function isAvailable(string $serviceName): bool
    {
        $failures = $this->getFailures($serviceName);
        $maxFailures = $this->getMaxFailures($serviceName);
        if ($failures < $maxFailures) {
            // this is what happens most of the time so we evaluate first
            return true;
        } else {
            $lastTest = $this->getLastTest($serviceName);
            $retryTimeout = $this->getRetryTimeout($serviceName);
            if ($lastTest + $retryTimeout < time()) {
                // Once we wait $retryTimeout, we have to allow one
                // thread to try to connect again. To prevent all other threads
                // from flooding, the potentially dead db, we update the time first
                // and then try to connect. If db is dead only one thread will hang
                // waiting for the connection. Others will get updated timeout from stats.
                //
                // 'Race condition' is between first thread getting into this line and
                // time it takes to store the settings. In that time other threads will
                // also be entering this statement. Even on very busy servers it
                // wont allow more than a few requests to get through before stats are updated.
                //
                // updating lastTest
                $this->setFailures($serviceName, $failures);
                // allowing this thread to try to connect to the resource
                return true;
            } else {
                return false;
            }
        }
    }

    // ---------------------- Directly accessed by interface methods -----------------

    /**
     * @param string $serviceName
     *
     * @return int
     */
    protected function getFailures(string $serviceName)
    {
        //FIXME - catch exceptions and replace implementation if occur
        return (int) $this->storageAdapter->loadStatus($serviceName, 'failures');
    }

    /**
     * @param string $serviceName
     *
     * @return int
     */
    protected function getMaxFailures(string $serviceName)
    {
        return $this->getSetting($serviceName, 'maxFailures');
    }

    /**
     * Load setting or initialise service name with defaults for faster lookups
     *
     * @param string $serviceName what service to look for
     * @param string $variable what setting to look for
     *
     * @return int
     */
    private function getSetting(string $serviceName, string $variable)
    {
        // make sure there are settings for the service
        if (!isset($this->settings[$serviceName])) {
            $this->settings[$serviceName] = array(
                'maxFailures'  => $this->defaultMaxFailures,
                'retryTimeout' => $this->defaultRetryTimeout,
            );
        }
        return $this->settings[$serviceName][$variable];
    }

    /**
     * @param string $serviceName
     *
     * @return int
     */
    protected function getLastTest(string $serviceName)
    {
        //FIXME - catch exceptions and replace implementation if occur
        return (int) $this->storageAdapter->loadStatus($serviceName, 'lastTest');
    }

    /**
     * @param string $serviceName
     *
     * @return int
     */
    protected function getRetryTimeout(string $serviceName)
    {
        return $this->getSetting($serviceName, 'retryTimeout');
    }


    /**
     * @param string $serviceName
     * @param integer $newValue
     */
    protected function setFailures(string $serviceName, $newValue)
    {
        //FIXME - catch exceptions and replace implementation if occur
        $this->storageAdapter->saveStatus($serviceName, 'failures', $newValue, false);
        // make sure storage adapter flushes changes this time
        $this->storageAdapter->saveStatus($serviceName, 'lastTest', time(), true);
    }


    public function reportFailure(string $serviceName)
    {
        // there is no science here, we always increase failures count
        $this->setFailures($serviceName, $this->getFailures($serviceName) + 1);
    }


    public function reportSuccess(string $serviceName)
    {
        $failures = $this->getFailures($serviceName);
        $maxFailures = $this->getMaxFailures($serviceName);
        if ($failures > $maxFailures) {
            // there were more failures than max failures
            // we have to reset failures count to max-1
            $this->setFailures($serviceName, $maxFailures - 1);
        } elseif ($failures > 0) {
            // if we are between max and 0 we decrease by 1 on each
            // success so we will go down to 0 after some time
            // but we are still more sensitive to failures
            $this->setFailures($serviceName, $failures - 1);
        } else {
            // if there are no failures reported we do not
            // have to do anything on success (system operational)
        }
    }

}
