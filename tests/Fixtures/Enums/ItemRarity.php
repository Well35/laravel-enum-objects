<?php

namespace Well35\EnumObjects\Tests\Fixtures\Enums;

use Well35\EnumObjects\Attributes\ComputedProperty;
use Well35\EnumObjects\Attributes\ObjectProperty;
use Well35\EnumObjects\Tests\Fixtures\PropertyKey;

#[ObjectProperty('group', 'items')]
enum ItemRarity: string
{
    #[ObjectProperty('icon', 'circle')]
    #[ObjectProperty('is_public', true)]
    #[ObjectProperty(PropertyKey::Description, 'A common item')]
    case Common = 'common';

    #[ObjectProperty('icon', 'sparkle')]
    #[ObjectProperty('color', '#override')]
    #[ObjectProperty('group', 'special')]
    case Legendary = 'legendary';

    #[ComputedProperty]
    public function color(): string
    {
        return match ($this) {
            self::Common => '#999',
            self::Legendary => '#f90',
        };
    }

    #[ComputedProperty('sortWeight')]
    public function weight(): int
    {
        return match ($this) {
            self::Common => 1,
            self::Legendary => 10,
        };
    }
}
