<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Architecture;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;
use Symfony\Component\Finder\Finder;

/**
 * Flags migration files whose down() method is empty or throws — these break `migrate:rollback`.
 */
final class MigrationDownCheck implements Check
{
    public function id(): string
    {
        return 'architecture/missing-migration-down';
    }

    public function category(): string
    {
        return Category::ARCHITECTURE;
    }

    public function description(): string
    {
        return 'Migraciones con down() vacío o que lanza excepción no se pueden revertir con seguridad.';
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
            if (!preg_match('/function\s+down\s*\(\s*\)\s*[^{]*\{(?P<body>(?:[^{}]|\{[^{}]*\})*)\}/s', $contents, $m)) {
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::LOW,
                    message: 'La migración no tiene método down().',
                    file: $context->relativePath($file->getRealPath()),
                    line: 1,
                    suggestion: 'Implementa down() para que la migración sea reversible.',
                );
                continue;
            }
            $body = trim($m['body']);
            if ($body === '' || preg_match('/^\s*(?:\/\/.*)?$/s', $body)) {
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::LOW,
                    message: 'El método down() de la migración está vacío.',
                    file: $context->relativePath($file->getRealPath()),
                    line: 1,
                    suggestion: 'Implementa down() (ej. Schema::dropIfExists(...)).',
                );
            }
        }
        return $findings;
    }
}
