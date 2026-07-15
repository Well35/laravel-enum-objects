<?php

namespace Well35\EnumObjects\Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use Well35\EnumObjects\EnumObjectsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $outputDir = __DIR__.'/output';

    protected function getPackageProviders($app): array
    {
        return [EnumObjectsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('enum-objects.paths', [
            'Well35\\EnumObjects\\Tests\\Fixtures\\Enums' => __DIR__.'/Fixtures/Enums',
        ]);
        $app['config']->set('enum-objects.output_path', $this->outputDir);
    }

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory($this->outputDir);
    }

    protected function generated(string $relative): string
    {
        return file_get_contents($this->outputDir.'/'.$relative);
    }
}
