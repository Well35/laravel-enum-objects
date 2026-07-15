<?php

namespace Well35\EnumObjects\Drivers;

interface Driver
{
    /**
     * Render one enum's generated file content.
     *
     * @param string $name  the enum's short name
     * @param class-string $class  the fully qualified enum class
     * @param array<string, array<string, mixed>> $cases  case name => object
     */
    public function render(string $name, string $class, array $cases): string;

    public function extension(): string;
}
