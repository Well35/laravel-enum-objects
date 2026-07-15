<?php

namespace Well35\EnumObjects\Tests\Fixtures;

/**
 * A consumer-defined key enum: lives outside the scanned Enums directory
 * on purpose, so it never generates a file of its own.
 */
enum PropertyKey: string
{
    case Description = 'description';
}
