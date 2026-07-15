<?php

namespace Well35\EnumObjects\Tests\Fixtures\Enums;

use Well35\EnumObjects\Attributes\Excluded;

enum Priority: int
{
    case Low = 1;
    case VeryHigh = 10;

    #[Excluded]
    case Internal = 99;
}
