<?php

namespace Well35\EnumObjects\Drivers;

use Well35\EnumObjects\Exceptions\EnumObjectsException;

final readonly class TypeScriptDriver implements Driver
{
    public function __construct(
        private ?string $valueKey,
    ) {}

    public function render(string $name, string $class, array $cases): string
    {
        $stub = file_get_contents(__DIR__.'/../../stubs/enum.ts.stub');

        if ($stub === false) {
            throw new EnumObjectsException('Unable to read the enum.ts stub.');
        }

        return strtr(str_replace("\r\n", "\n", $stub), [
            '{{ class }}' => $class,
            '{{ name }}' => $name,
            '{{ body }}' => $this->body($cases),
            '{{ valueAccessor }}' => $this->valueAccessor(),
        ]);
    }

    private function valueAccessor(): string
    {
        return $this->valueKey === null
            ? ''
            : '['.json_encode($this->valueKey, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).']';
    }

    public function extension(): string
    {
        return 'ts';
    }

    /** @param array<string, array<string, mixed>> $cases */
    private function body(array $cases): string
    {
        return implode("\n", array_map(
            fn (string $caseName, array $object): string => "    {$caseName}: { {$this->properties($object)} },",
            array_keys($cases),
            $cases,
        ));
    }

    /** @param array<string, mixed> $object */
    private function properties(array $object): string
    {
        $pairs = [];

        foreach ($object as $key => $value) {
            $pairs[] = $this->key($key).': '.$this->value($value);
        }

        return implode(', ', $pairs);
    }

    private function key(string $key): string
    {
        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $key)
            ? $key
            : json_encode($key, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function value(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
