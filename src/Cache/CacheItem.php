<?php

namespace Rikudou\MemoizeBundle\Cache;

use DateInterval;
use DateTimeInterface;
use LogicException;
use Psr\Cache\CacheItemInterface;

class CacheItem implements CacheItemInterface
{
    private ?bool $isHit = null;

    public function __construct(
        private readonly string $key,
        private mixed $value = null,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return (bool) $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(DateInterval|int|null $time): static
    {
        return $this;
    }

    /**
     * @internal
     */
    public function setIsHit(bool $isHit): void
    {
        if ($this->isHit !== null) {
            throw new LogicException('This method can only be called once and has already been called');
        }

        $this->isHit = $isHit;
    }
}