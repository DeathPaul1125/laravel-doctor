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
        return 'Múltiples migraciones crean la misma tabla — posible duplicado o artefacto de merge.';
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
                    message: 'La tabla "' . $table . '" se crea en ' . count($files) . ' migraciones: ' . implode(', ', $files),
                    file: $files[0],
                    suggestion: 'Consolida en una sola migración Schema::create y usa Schema::table en las migraciones posteriores para modificarla.',
                );
            }
        }
        return $findings;
    }
}
