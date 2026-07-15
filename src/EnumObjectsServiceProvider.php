<?php

namespace Well35\EnumObjects;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Well35\EnumObjects\Commands\GenerateCommand;

class EnumObjectsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/enum-objects.php', 'enum-objects');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/enum-objects.php' => config_path('enum-objects.php'),
            ], 'enum-objects-config');

            $this->commands([GenerateCommand::class]);
        }

        if (class_exists(AboutCommand::class)) {
            AboutCommand::add('Enum Objects', fn () => [
                'Format' => config('enum-objects.format'),
                'Output Path' => config('enum-objects.output_path'),
            ]);
        }
    }
}
