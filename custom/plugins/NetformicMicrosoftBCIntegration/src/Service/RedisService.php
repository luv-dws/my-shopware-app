<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Shopware\Core\Framework\Adapter\Redis\RedisConnectionProvider;

/**
 * Provides helper methods for interacting with the configured Redis cache.
 *
 * This service is responsible for storing, retrieving, and removing
 * cached product data used by the Microsoft Business Central integration.
 */
class RedisService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RedisConnectionProvider $cache,
        private string $connectionName = 'redis_cache'
    ) {
    }

    /**
     * Stores data in Redis with the specified expiration time.
     *
     * @param string $key
     * @param mixed $data
     * @param int $expirations Cache lifetime in seconds.
     *
     * @return void
     */
    public function create($key, $data, $expirations = 7200)
    {
        if ($this->cache->hasConnection($this->connectionName)) {
            $connection = $this->cache->getConnection($this->connectionName);
            $connection->setex($key, $expirations, json_encode($data));
        }
    }

    /**
     * Retrieves cached data from Redis.
     *
     * Returns an empty array if the key does not exist or Redis
     * is unavailable.
     *
     * @param string $key
     *
     * @return array
     */
    public function fetch($key)
    {
        if ($this->cache->hasConnection($this->connectionName)) {
            $connection = $this->cache->getConnection($this->connectionName);
            $data = $connection->get($key);

            return $data ? json_decode($data, true) : [];
        }

        return [];
    }

    /**
     * Retrieves multiple cached items from Redis in a single bulk mget query.
     *
     * @param array<int, string> $keys List of Redis keys
     * @return array<string, array> Map of key => decoded cached data
     */
    public function fetchMultiple(array $keys): array
    {
        if (empty($keys) || !$this->cache->hasConnection($this->connectionName)) {
            return [];
        }

        $connection = $this->cache->getConnection($this->connectionName);
        $results = $connection->mget(array_values($keys));

        if (!is_array($results)) {
            return [];
        }

        $data = [];
        $keysList = array_values($keys);

        foreach ($results as $index => $rawData) {
            $key = $keysList[$index] ?? null;
            if ($key !== null && !empty($rawData) && is_string($rawData)) {
                $decoded = json_decode($rawData, true);
                if (is_array($decoded)) {
                    $data[$key] = $decoded;
                }
            }
        }

        return $data;
    }

    /**
     * Stores multiple items in Redis using pipeline.
     *
     * @param array<string, mixed> $items Map of key => data
     * @param int $expirations Cache lifetime in seconds.
     * @return void
     */
    public function createMultiple(array $items, int $expirations = 7200): void
    {
        if (empty($items) || !$this->cache->hasConnection($this->connectionName)) {
            return;
        }

        $connection = $this->cache->getConnection($this->connectionName);

        if (method_exists($connection, 'pipeline')) {
            $pipe = $connection->pipeline();
            foreach ($items as $key => $data) {
                $pipe->setex($key, $expirations, json_encode($data));
            }
            $pipe->exec();
        } else {
            foreach ($items as $key => $data) {
                $connection->setex($key, $expirations, json_encode($data));
            }
        }
    }

    /**
     * Deletes one or more cache entries from Redis.
     *
     * @param string|array $keys
     *
     * @return int Number of deleted keys.
     */
    public function delete($keys)
    {
        if ($this->cache->hasConnection($this->connectionName)) {
            $connection = $this->cache->getConnection($this->connectionName);

            return $connection->del($keys);
        }

        return 0;
    }

    /**
     * Removes all entries from the configured Redis database.
     *
     * @return bool|null
     */
    public function flushAll()
    {
        if ($this->cache->hasConnection($this->connectionName)) {
            $connection = $this->cache->getConnection($this->connectionName);

            return $connection->flushall();
        }

        return null;
    }

    /**
     * Deletes all Redis keys matching the provided prefixes.
     *
     * @param array $prefixes
     *
     * @return int Number of deleted keys.
     */
    public function flushByPrefix(array $prefixes)
    {
        if ($this->cache->hasConnection($this->connectionName)) {
            $connection = $this->cache->getConnection($this->connectionName);
            $deletedCount = 0;

            foreach ($prefixes as $prefix) {
                $keys = $connection->keys($prefix . '*');

                if (!empty($keys)) {
                    $deletedCount += $connection->del($keys);
                }
            }

            return $deletedCount;
        }

        return 0;
    }
}