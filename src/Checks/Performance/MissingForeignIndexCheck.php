<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Performance;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;
use LaravelDoctor\Support\LineLocator;
use Symfony\Component\Finder\Finder;

/**
 * In migrations, flags columns ending in _id (or using ->foreignId) that are not declared
 * as ->index() / ->foreign() / ->constrained() / ->unique() / primary().
 */
final class MissingForeignIndexCheck implements Check
{
    public function id(): string
    {
        return 'performance/missing-fk-index';
    }

    public function category(): string
    {
        return Category::PERFORMANCE;
    }

    public function description(): string
    {
        return 'Columnas de llave foránea sin índice escanean toda la tabla en cada JOIN.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $dir = $context->projectRoot . '/database/migrations';
        if (!is_dir($dir)) {
            return $findings;
        }
        $finder = (new Finder())->files()->in($dir)->name('*.php');
        foreach ($finder as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            $rel = $context->relativePath($file->getRealPath());

            // Capture lines like: $table->unsignedBigInteger('user_id'); without index/foreign/constrained
            $pattern = '/\$table->(?:unsignedBigInteger|unsignedInteger|bigInteger|integer)\s*\(\s*[\'"]([a-zA-Z_][a-zA-Z0-9_]*_id)[\'"][^;]*;/';
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $i => $m) {
                    $stmt = $m[0];
                    $col = $matches[1][$i][0];
                    if (preg_match('/->(?:index|foreign|constrained|primary|unique)\s*\(/', $stmt)) {
                        continue;
                    }
                    // Also acceptable: a separate $table->index('col') / foreign call elsewhere in the file
                    if (preg_match('/->(?:index|foreign|unique)\s*\(\s*[\'"]' . preg_quote($col, '/') . '[\'"]/', $contents)) {
                        continue;
                    }
                    $line = LineLocator::lineFromOffset($contents, $m[1]);
                    $findings[] = new Finding(
                        checkId: $this->id(),
                        category: $this->category(),
                        severity: Severity::MEDIUM,
                        message: 'La columna ' . $col . ' parece ser una llave foránea pero no tiene índice.',
                        file: $rel,
                        line: $line,
                        suggestion: 'Encadena ->index() o usa $table->foreignId(\'' . $col . '\')->constrained().',
                        snippet: LineLocator::snippetAround($contents, $m[1]),
                    );
                }
            }
        }
        return $findings;
    }
}
