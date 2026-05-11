<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Quality;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;
use Symfony\Component\Finder\Finder;

/**
 * Detects database/migrations files that create the same table more than once,
 * a frequent source of "table already exists" errors.
 */
final class DuplicateMigrationCheck implements Check
{
    public function id(): string
    {
        return 'quality/duplicate-migration';
    }

    public function category(): string
    {
        return Category::QUALITY;
    }

    public function description(): string
    {
        return 'Multiple migrations create the same table — likely a duplicate or merge artifact.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $dir = $context->projectRoot . '/database/migrations';
        if (!is_dir($dir)) {
            return $findings;
        }

        $byTable = [];
        $finder = (new Finder())->files()->in($dir)->name('*.php');
        foreach ($finder as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            if (preg_match_all('/Schema::create\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                foreach ($m[1] as $table) {
                    $byTable[$table][] = $context->relativePath($file->getRealPath());
                }
            }
        }

        foreach ($byTable as $table => $files) {
            if (count($files) > 1) {
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::MEDIUM,
                    message: 'Table "' . $table . '" is created in ' . count($files) . ' migrations: ' . implode(', ', $files),
                    file: $files[0],
                    suggestion: 'Consolidate into a single Schema::create migration and use Schema::table to modify in later migrations.',
                );
            }
        }
        return $findings;
    }
}
