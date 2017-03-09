<?php
declare(strict_types = 1);


namespace Paymaxi\Component\CircuitBreaker\Storage\Adapter;


use Psr\Cache\CacheItemPoolInterface;

final class PsrCacheAdapter extends BaseAdapter
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cacheItemPool;

    /**
     * @param CacheItemPoolInterface $cacheItemPool
     * @param int $ttl
     * @param string $cachePrefix
     */
    public function __construct(CacheItemPoolInterface $cacheItemPool, int $ttl = 3600, string $cachePrefix = null)
    {
        $this->cacheItemPool = $cacheItemPool;
        parent::__construct($ttl, $cachePrefix);
    }

    /**
     * Helper method to make sure that extension is loaded (implementation dependent)
     *
     * @throws \Paymaxi\Component\CircuitBreaker\Storage\StorageException if extension is not loaded
     * @return void
     */
    protected function checkExtension()
    {
    }

    /**
     * Loads item by cache key.
     *
     * @param string $key
     *
     * @return mixed
     *
     * @throws \Paymaxi\Component\CircuitBreaker\Storage\StorageException if storage error occurs, handler can not be used
     */
    protected function load($key)
    {
        $cacheItem = $this->cacheItemPool->getItem($key);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        return "";
    }

    /**
     * Save item in the cache.
     *
     * @param string $key
     * @param string $value
     * @param int $ttl
     *
     * @return void
     *
     * @throws \Paymaxi\Component\CircuitBreaker\Storage\StorageException if storage error occurs, handler can not be used
     */
    protected function save($key, $value, $ttl)
    {
        $cacheItem = $this->cacheItemPool->getItem($key);
        $cacheItem->set($value)->expiresAfter($ttl);
        $this->cacheItemPool->save($cacheItem);
    }
}