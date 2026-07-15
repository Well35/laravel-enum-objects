<?php

namespace Well35\EnumObjects\Tests\Fixtures\Invalid;

use Well35\EnumObjects\Attributes\ComputedProperty;

enum BadComputedProperty: string
{
    case Only = 'only';

    #[ComputedProperty]
    public function needsArgument(string $input): string
    {
        return $input;
    }
}
