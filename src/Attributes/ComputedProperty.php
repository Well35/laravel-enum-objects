<?php

namespace Well35\EnumObjects\Attributes;

use Attribute;
use BackedEnum;

/**
 * Adds a computed property to every case's generated object by calling
 * this method per case. The property key defaults to the method name.
 * Pass $key to override.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ComputedProperty
{
    public function __construct(
        public string|BackedEnum|null $key = null,
    ) {}
}
