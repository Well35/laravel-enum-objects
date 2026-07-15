<?php

namespace Well35\EnumObjects\Attributes;

use Attribute;

/**
 * Excludes one case from the generated object. When placed on the enum class
 * instead, excludes the whole enum from generation.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Excluded {}
