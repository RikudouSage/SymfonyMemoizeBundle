<?php

namespace Rikudou\MemoizeBundle\Cache\KeySpecifier;

use Rikudou\MemoizeBundle\Cache\KeySpecifier\CacheKeySpecifier;

final class NullCacheKeySpecifier implements CacheKeySpecifier
{
    public function generate(): string
    {
        return '';
    }
}
