<?php

namespace Well35\EnumObjects;

use ReflectionEnum;
use ReflectionException;
use Well35\EnumObjects\Attributes\Excluded;
use Well35\EnumObjects\Drivers\Driver;
use Well35\EnumObjects\Drivers\JsonDriver;
use Well35\EnumObjects\Drivers\TypeScriptDriver;
use Well35\EnumObjects\Exceptions\EnumObjectsException;

/**
 * Pruning only deletes files listed in the manifest (.enum-objects.json),
 * never anything the package didn't write
 */
final class Generator
{
    private const string MANIFEST = '.enum-objects.json';

    /** @param array<string, string> $paths namespace => absolute directory */
    public function __construct(
        private readonly array $paths,
        private readonly string $outputPath,
        private readonly Driver $driver,
        private readonly string $labelMethod,
        private readonly ?string $nameKey,
        private readonly ?string $valueKey,
        private readonly ?string $labelKey,
    ) {}

    public static function fromConfig(?string $formatOverride = null): self
    {
        $config = config('enum-objects');

        if (! is_array($config)) {
            throw new EnumObjectsException('The enum-objects config is missing.');
        }

        $configuredPaths = $config['paths'] ?? null;

        if (! is_array($configuredPaths)) {
            throw new EnumObjectsException('enum-objects.paths must be an array of namespace => directory.');
        }

        $paths = [];

        foreach ($configuredPaths as $namespace => $directory) {
            if (! is_string($namespace) || ! is_string($directory)) {
                throw new EnumObjectsException('enum-objects.paths must map namespace strings to directory strings.');
            }

            $paths[$namespace] = self::absolute($directory);
        }

        $format = $formatOverride ?? self::stringOption($config, 'format');
        $nameKey = self::keyOption($config, 'name_key');
        $valueKey = self::keyOption($config, 'value_key');
        $labelKey = self::keyOption($config, 'label_key');

        $keys = array_filter([$nameKey, $valueKey, $labelKey]);

        if (count($keys) !== count(array_unique($keys))) {
            throw new EnumObjectsException('enum-objects built-in key names must be unique.');
        }

        $driver = match ($format) {
            'ts' => new TypeScriptDriver($valueKey),
            'json' => new JsonDriver(),
            default => throw new EnumObjectsException("Unknown enum-objects format: {$format}."),
        };

        return new self(
            paths: $paths,
            outputPath: self::absolute(self::stringOption($config, 'output_path')),
            driver: $driver,
            labelMethod: self::stringOption($config, 'label_method'),
            nameKey: $nameKey,
            valueKey: $valueKey,
            labelKey: $labelKey,
        );
    }

    /** @param array<array-key, mixed> $config */
    private static function stringOption(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new EnumObjectsException("enum-objects.{$key} must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function keyOption(array $config, string $key): ?string
    {
        if (! array_key_exists($key, $config)) {
            throw new EnumObjectsException("enum-objects.{$key} is missing from the config.");
        }

        $value = $config[$key];

        if ($value !== null && (! is_string($value) || $value === '')) {
            throw new EnumObjectsException("enum-objects.{$key} must be a non-empty string or null.");
        }

        return $value;
    }

    /**
     * @return array{files: array<string, string>, warnings: list<string>}
     * @throws ReflectionException
     */
    public function plan(): array
    {
        $builder = new ObjectBuilder($this->labelMethod, $this->nameKey, $this->valueKey, $this->labelKey);
        $files = [];
        $warnings = [];

        foreach (new EnumLocator($this->paths)->enums() as $relative => $class) {
            $enum = new ReflectionEnum($class);

            if ($enum->getAttributes(Excluded::class) !== []) {
                continue;
            }

            if (! $enum->isBacked()) {
                $warnings[] = "{$class} is a pure enum, its generated value is the case name, which PHP cannot json_encode. Consider backing the enum with a string.";
            }

            $name = basename($relative);
            $files[$relative.'.'.$this->driver->extension()] = $this->driver->render($name, $class, $builder->build($class));
        }

        return ['files' => $files, 'warnings' => $warnings];
    }

    /**
     * @return list<string>
     * @throws ReflectionException
     */
    public function check(): array
    {
        $planned = $this->plan()['files'];
        $drift = [];

        foreach ($planned as $relative => $content) {
            $target = $this->outputPath.'/'.$relative;

            if (! is_file($target)) {
                $drift[] = "missing: {$relative}";
            } elseif ($this->normalize($this->contents($target)) !== $this->normalize($content)) {
                $drift[] = "stale: {$relative}";
            }
        }

        foreach ($this->manifestFiles() as $relative) {
            if (! isset($planned[$relative]) && is_file($this->outputPath.'/'.$relative)) {
                $drift[] = "orphaned: {$relative}";
            }
        }

        $manifestPath = $this->outputPath.'/'.self::MANIFEST;
        $expected = $this->renderManifest(array_keys($planned));

        if (! is_file($manifestPath)) {
            $drift[] = 'missing: '.self::MANIFEST;
        } elseif ($this->normalize($this->contents($manifestPath)) !== $this->normalize($expected)) {
            $drift[] = 'stale: '.self::MANIFEST;
        }

        return $drift;
    }

    /**
     * @return array{written: list<string>, unchanged: list<string>, pruned: list<string>, warnings: list<string>}
     * @throws ReflectionException
     */
    public function write(): array
    {
        $plan = $this->plan();
        $previous = $this->manifestFiles();
        $report = ['written' => [], 'unchanged' => [], 'pruned' => [], 'warnings' => $plan['warnings']];

        foreach ($plan['files'] as $relative => $content) {
            $target = $this->outputPath.'/'.$relative;

            if (is_file($target) && $this->normalize($this->contents($target)) === $this->normalize($content)) {
                $report['unchanged'][] = $relative;
                continue;
            }

            $directory = dirname($target);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, recursive: true);
            }

            file_put_contents($target, $content);
            $report['written'][] = $relative;
        }

        foreach ($previous as $relative) {
            $target = $this->outputPath.'/'.$relative;

            if (! isset($plan['files'][$relative]) && is_file($target)) {
                unlink($target);
                $this->removeEmptyDirectories(dirname($target));
                $report['pruned'][] = $relative;
            }
        }

        $manifestPath = $this->outputPath.'/'.self::MANIFEST;
        $manifest = $this->renderManifest(array_keys($plan['files']));

        if (! is_file($manifestPath) || $this->normalize($this->contents($manifestPath)) !== $this->normalize($manifest)) {
            if (! is_dir($this->outputPath)) {
                mkdir($this->outputPath, 0755, recursive: true);
            }

            file_put_contents($manifestPath, $manifest);
        }

        return $report;
    }

    /** @return list<string> */
    private function manifestFiles(): array
    {
        $path = $this->outputPath.'/'.self::MANIFEST;

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode($this->contents($path), associative: true);
        $files = is_array($decoded) ? ($decoded['files'] ?? []) : [];

        return is_array($files) ? array_values(array_filter($files, is_string(...))) : [];
    }

    /** @param list<string> $files */
    private function renderManifest(array $files): string
    {
        return json_encode([
            'generated_by' => 'well35/laravel-enum-objects',
            'files' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new EnumObjectsException("Unable to read {$path}.");
        }

        return $contents;
    }

    private function removeEmptyDirectories(string $directory): void
    {
        $root = rtrim(str_replace('\\', '/', $this->outputPath), '/');

        while (str_replace('\\', '/', $directory) !== $root && @rmdir($directory)) {
            $directory = dirname($directory);
        }
    }

    private function normalize(string $content): string
    {
        return str_replace("\r\n", "\n", $content);
    }

    private static function absolute(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/')
            || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '/' || $path[2] === '\\'));

        return $isAbsolute ? $path : base_path($path);
    }
}
