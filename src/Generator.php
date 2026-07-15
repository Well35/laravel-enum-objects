<?php

namespace Well35\EnumObjects;

use ReflectionEnum;
use ReflectionException;
use Well35\EnumObjects\Attributes\Excluded;
use Well35\EnumObjects\Drivers\Driver;
use Well35\EnumObjects\Drivers\JsonDriver;
use Well35\EnumObjects\Drivers\TypeScriptDriver;

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
        private readonly string $labelMethod = 'label',
    ) {}

    public static function fromConfig(?string $formatOverride = null): self
    {
        $config = config('enum-objects');

        $paths = array_map(function ($path) {
            return self::absolute($path);
        }, $config['paths']);

        $format = $formatOverride ?? $config['format'];

        $driver = match ($format) {
            'ts' => new TypeScriptDriver(),
            'json' => new JsonDriver(),
            default => throw new EnumObjectsException("Unknown enum-objects format: {$format}."),
        };

        return new self(
            paths: $paths,
            outputPath: self::absolute($config['output_path']),
            driver: $driver,
            labelMethod: $config['label_method'] ?? 'label',
        );
    }

    /**
     * @return array{files: array<string, string>, warnings: list<string>}
     * @throws ReflectionException
     */
    public function plan(): array
    {
        $builder = new ObjectBuilder($this->labelMethod);
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
            } elseif ($this->normalize(file_get_contents($target)) !== $this->normalize($content)) {
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
        } elseif ($this->normalize(file_get_contents($manifestPath)) !== $this->normalize($expected)) {
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

            if (is_file($target) && $this->normalize(file_get_contents($target)) === $this->normalize($content)) {
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

        if (! is_file($manifestPath) || $this->normalize(file_get_contents($manifestPath)) !== $this->normalize($manifest)) {
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

        $decoded = json_decode(file_get_contents($path), associative: true);
        $files = $decoded['files'] ?? [];

        return is_array($files) ? array_values(array_filter($files, is_string(...))) : [];
    }

    /** @param list<string> $files */
    private function renderManifest(array $files): string
    {
        return json_encode([
            'generated_by' => 'well35/laravel-enum-objects',
            'files' => array_values($files),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
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
