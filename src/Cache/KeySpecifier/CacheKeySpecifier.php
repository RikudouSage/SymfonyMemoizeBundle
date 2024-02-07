<?php

namespace Rikudou\MemoizeBundle\Cache\KeySpecifier;

interface CacheKeySpecifier
{
    public function generate(): string;
}
