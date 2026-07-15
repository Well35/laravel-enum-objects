<?php

namespace Well35\EnumObjects\Tests\Fixtures\Enums;

enum BrowseSort: string
{
    case Newest = 'newest';
    case Popular = 'popular';

    public function label(): string
    {
        return match ($this) {
            self::Newest => 'Newest',
            self::Popular => 'Most played',
        };
    }
}
