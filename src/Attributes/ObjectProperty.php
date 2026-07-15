<?php

namespace Well35\EnumObjects\Attributes;

use Attribute;
use BackedEnum;

/**
 * Adds a static property to one case's generated object. When placed on the
 * enum class instead, it adds it to every case's object.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final readonly class ObjectProperty
{
    public function __construct(
        public string|BackedEnum $key,
        public string|int|float|bool|array|null $value,
    ) {}
}
