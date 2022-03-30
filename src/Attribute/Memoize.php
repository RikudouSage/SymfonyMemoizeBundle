<?php

namespace Rikudou\MemoizeBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Memoize
{
    /**
     * @param array<string> $environments
     */
    public function __construct(
        public readonly ?int $seconds = null,
        public readonly array $environments = [Memoizable::ALL_ENVIRONMENTS],
    ) {
    }
}
