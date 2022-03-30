<?php

namespace Rikudou\MemoizeBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class NoMemoize
{
    /**
     * @param array<string> $environments
     */
    public function __construct(
        public readonly array $environments = [Memoizable::ALL_ENVIRONMENTS],
    ) {
    }
}
