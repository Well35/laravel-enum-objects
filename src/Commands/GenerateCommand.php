<?php

namespace Well35\EnumObjects\Commands;

use Illuminate\Console\Command;
use ReflectionException;
use Well35\EnumObjects\Generator;

class GenerateCommand extends Command
{
    protected $signature = 'enum-objects:generate
        {--check : Exit 1 if any generated file is missing, stale, or orphaned}
        {--format= : Override the configured format (ts or json)}
        {--paths : Print the configured enum directories as JSON}';

    protected $description = 'Generate frontend enum objects from PHP enums';

    /**
     * @throws ReflectionException
     */
    public function handle(): int
    {
        if ($this->option('paths')) {
            $this->line(json_encode(array_values(config('enum-objects.paths'))));

            return self::SUCCESS;
        }

        $generator = Generator::fromConfig($this->option('format'));

        if ($this->option('check')) {
            return $this->check($generator);
        }

        $report = $generator->write();

        foreach ($report['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        foreach ($report['written'] as $file) {
            $this->components->twoColumnDetail($file, '<fg=green>written</>');
        }

        foreach ($report['pruned'] as $file) {
            $this->components->twoColumnDetail($file, '<fg=red>pruned</>');
        }

        $this->components->info(sprintf(
            '%d written, %d unchanged, %d pruned.',
            count($report['written']),
            count($report['unchanged']),
            count($report['pruned']),
        ));

        return self::SUCCESS;
    }

    /**
     * @throws ReflectionException
     */
    private function check(Generator $generator): int
    {
        $drift = $generator->check();

        if ($drift === []) {
            $this->components->info('Enum objects are in sync.');

            return self::SUCCESS;
        }

        foreach ($drift as $line) {
            [$status, $file] = explode(': ', $line, 2);
            $this->components->twoColumnDetail($file, "<fg=red>{$status}</>");
        }

        $this->components->error('Enum objects are out of sync. Run `php artisan enum-objects:generate`.');

        return self::FAILURE;
    }
}
