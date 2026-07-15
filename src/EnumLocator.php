<?php

namespace Well35\EnumObjects;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Maps configured namespace-to-directory pairs onto enum classes by
 * globbing .php files
 */
final readonly class EnumLocator
{
    /** @param array<string, string> $paths */
    public function __construct(
        private array $paths,
    ) {}

    /**
     * @return array<string, class-string<\UnitEnum>>
     */
    public function enums(): array
    {
        $enums = [];

        foreach ($this->paths as $namespace => $directory) {
            if (! is_dir($directory)) {
                throw new EnumObjectsException(
                    "Configured enum path does not exist: {$directory} (namespace {$namespace})"
                );
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = $this->relativePath($file->getPathname(), $directory);
                $class = $this->expectedClass($relative, $namespace);

                if (enum_exists($class)) {
                    $enums[$relative] = $class;
                }
            }
        }

        ksort($enums);

        return $enums;
    }

    private function relativePath(string $pathname, string $directory): string
    {
        $insideDirectory = substr($pathname, strlen(rtrim($directory, '/\\')) + 1);
        $withoutExtension = substr($insideDirectory, 0, -strlen('.php'));

        return str_replace('\\', '/', $withoutExtension);
    }

    private function expectedClass(string $relative, string $namespace): string
    {
        return rtrim($namespace, '\\').'\\'.str_replace('/', '\\', $relative);
    }
}
