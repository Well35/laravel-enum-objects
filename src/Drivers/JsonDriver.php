<?php

namespace Well35\EnumObjects\Drivers;

final class JsonDriver implements Driver
{
    public function render(string $name, string $class, array $cases): string
    {
        return json_encode(
            $cases,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        )."\n";
    }

    public function extension(): string
    {
        return 'json';
    }
}
