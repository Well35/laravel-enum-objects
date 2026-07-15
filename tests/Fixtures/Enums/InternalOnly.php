<?php

namespace Well35\EnumObjects\Tests\Fixtures\Enums;

use Well35\EnumObjects\Attributes\Excluded;

#[Excluded]
enum InternalOnly: string
{
    case Secret = 'secret';
}
