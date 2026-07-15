<?php

namespace Well35\EnumObjects\Drivers;

final class TypeScriptDriver implements Driver
{
    public function render(string $name, string $class, array $cases): string
    {
        $stub = str_replace("\r\n", "\n", file_get_contents(__DIR__.'/../../stubs/enum.ts.stub'));

        return strtr($stub, [
            '{{ class }}' => $class,
            '{{ name }}' => $name,
            '{{ body }}' => $this->body($cases),
        ]);
    }

    public function extension(): string
    {
        return 'ts';
    }

    private function body(array $cases): string
    {
        return implode("\n", array_map(
            fn (string $caseName, array $object): string => "    {$caseName}: { {$this->properties($object)} },",
            array_keys($cases),
            $cases,
        ));
    }

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
            : json_encode($key, JSON_UNESCAPED_UNICODE);
    }

    private function value(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
