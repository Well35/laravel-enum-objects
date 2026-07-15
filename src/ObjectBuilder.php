<?php

namespace Well35\EnumObjects;

use BackedEnum;
use Illuminate\Support\Str;
use ReflectionEnum;
use ReflectionMethod;
use UnitEnum;
use Well35\EnumObjects\Attributes\ComputedProperty;
use Well35\EnumObjects\Attributes\Excluded;
use Well35\EnumObjects\Attributes\ObjectProperty;
use Well35\EnumObjects\Exceptions\EnumObjectsException;

/**
 * Turns one enum class into a plain array of per-case objects.
 */
final readonly class ObjectBuilder
{
    public function __construct(
        private string $labelMethod,
        private ?string $nameKey,
        private ?string $valueKey,
        private ?string $labelKey,
    ) {}

    /**
     * @param class-string<UnitEnum> $class
     * @return array<string, array<string, mixed>>
     * @throws \ReflectionException
     */
    public function build(string $class): array
    {
        $enum = new ReflectionEnum($class);
        $classProperties = $this->objectProperties($enum->getAttributes(ObjectProperty::class));
        $computedProperties = $this->computedProperties($enum);
        $hasLabel = $enum->hasMethod($this->labelMethod);

        $objects = [];

        foreach ($enum->getCases() as $reflectionCase) {
            if ($reflectionCase->getAttributes(Excluded::class) !== []) {
                continue;
            }

            $case = $reflectionCase->getValue();

            $object = [];

            if ($this->nameKey !== null) {
                $object[$this->nameKey] = $case->name;
            }

            if ($this->valueKey !== null) {
                $object[$this->valueKey] = $case instanceof BackedEnum ? $case->value : $case->name;
            }

            if ($this->labelKey !== null) {
                $object[$this->labelKey] = $hasLabel
                    ? $case->{$this->labelMethod}()
                    : Str::headline($case->name);
            }

            foreach ($classProperties as $key => $value) {
                $object[$key] = $value;
            }

            foreach ($computedProperties as $key => $method) {
                $object[$key] = $this->normalize($case->{$method}());
            }

            foreach ($this->objectProperties($reflectionCase->getAttributes(ObjectProperty::class)) as $key => $value) {
                $object[$key] = $value;
            }

            $objects[$case->name] = $object;
        }

        return $objects;
    }

    /**
     * @param ReflectionEnum<UnitEnum> $enum
     * @return array<string, string>
     */
    private function computedProperties(ReflectionEnum $enum): array
    {
        $properties = [];

        foreach ($enum->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(ComputedProperty::class) as $attribute) {
                if ($method->getNumberOfRequiredParameters() > 0) {
                    throw new EnumObjectsException(
                        "{$enum->getName()}::{$method->getName()}() has #[ComputedProperty] but requires arguments. Tagged methods must be callable with none."
                    );
                }

                $property = $attribute->newInstance();
                $properties[$this->keyOf($property->key ?? $method->getName())] = $method->getName();
            }
        }

        ksort($properties);

        return $properties;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof UnitEnum) {
            return $value instanceof BackedEnum ? $value->value : $value->name;
        }

        if (is_array($value)) {
            return array_map($this->normalize(...), $value);
        }

        return $value;
    }

    /**
     * @param list<\ReflectionAttribute<ObjectProperty>> $attributes
     * @return array<string, mixed>
     */
    private function objectProperties(array $attributes): array
    {
        $properties = [];

        foreach ($attributes as $attribute) {
            $property = $attribute->newInstance();
            $properties[$this->keyOf($property->key)] = $property->value;
        }

        return $properties;
    }

    private function keyOf(string|BackedEnum $key): string
    {
        return $key instanceof BackedEnum ? (string) $key->value : $key;
    }
}
