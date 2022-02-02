<?php

namespace Rikudou\MemoizeBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Memoize
{
    public function __construct(
        public readonly ?int $seconds = null,
    ) {
    }
}