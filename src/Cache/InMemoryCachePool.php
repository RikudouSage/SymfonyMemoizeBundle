<?php

namespace Rikudou\MemoizeBundle\Cache;

use JetBrains\PhpStorm\Pure;
use LogicException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

final class InMemoryCachePool implements CacheItemPoolInterface
{
    /**
     * @var array<CacheItem>
     */
    private array $items = [];

    #[Pure]
    public function getItem(string $key): CacheItemInterface
    {
        return $this->items[$key] ?? new CacheItem($key);
    }

    /**
     * @param array<string> $keys
     *
     * @throws InvalidArgumentException
     *
     * @return iterable<CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            throw new LogicException(sprintf("Only instances of '%s' can be handled", CacheItem::class));
        }
        $item->setIsHit(true);
        $this->items[$item->getKey()] = $item;

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}
