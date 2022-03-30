<?php

namespace Rikudou\MemoizeBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Memoizable
{
    public const ALL_ENVIRONMENTS = 'all';

    /**
     * @param array<string> $environments
     */
    public function __construct(
        public readonly array $environments = [self::ALL_ENVIRONMENTS],
    ) {
    }
}
