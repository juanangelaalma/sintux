<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeInertiaPage extends Command
{
    protected $name = 'make:inertia-page';

    protected $description = 'Create a new Inertia page in the centralized resources/js/pages/ directory.';

    public function handle(): int
    {
        $name = $this->argument('name');
        $parts = explode('/', str_replace('\\', '/', $name));

        $pageName = Str::studly(end($parts));
        $directory = strtolower(implode('/', array_slice($parts, 0, -1)));

        $baseDir = resource_path('js/pages');
        $targetDir = $directory ? $baseDir.'/'.$directory : $baseDir;
        $fileName = $pageName.'.tsx';
        $filePath = $targetDir.'/'.$fileName;

        if (file_exists($filePath) && ! $this->option('force')) {
            $this->error("File already exists: {$filePath}");

            return self::FAILURE;
        }

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $stub = file_get_contents(base_path('.stubs/inertia-page.tsx.stub'));
        if ($stub === false) {
            $this->error('Stub file not found.');

            return self::FAILURE;
        }

        $content = str_replace(
            '{{ pageName }}',
            $pageName,
            $stub
        );

        file_put_contents($filePath, $content);

        $this->info("Created: {$filePath}");

        return self::SUCCESS;
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'Page path (e.g., User/Index, Sales/Invoice/Create)'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file'],
        ];
    }
}
