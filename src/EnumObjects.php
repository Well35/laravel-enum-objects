<?php

namespace Well35\EnumObjects;

use PHPUnit\Framework\Assert;

final class EnumObjects
{
    /**
     * One-line CI guard for host apps. Fails the test when any generated
     * enum file is missing.
     *
     *     test('enum objects are in sync', fn () => EnumObjects::assertInSync());
     */
    public static function assertInSync(): void
    {
        $drift = Generator::fromConfig()->check();

        Assert::assertSame(
            [],
            $drift,
            "Enum objects are out of sync with PHP enums:\n  - ".implode("\n  - ", $drift).
            "\nRun `php artisan enum-objects:generate` and commit the result."
        );
    }
}
