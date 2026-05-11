<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Security;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;
use LaravelDoctor\Support\LineLocator;

/**
 * env() should only be called inside config/ files.
 * Calls elsewhere break `php artisan config:cache` and silently return null in production.
 */
final class EnvOutsideConfigCheck implements Check
{
    public function id(): string
    {
        return 'security/env-outside-config';
    }

    public function category(): string
    {
        return Category::SECURITY;
    }

    public function description(): string
    {
        return 'Llamadas a env() fuera de config/ rompen el cacheo de configuración en producción.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        foreach (['app', 'routes', 'database', 'tests'] as $dir) {
            foreach ($context->phpFiles($dir) as $file) {
                $contents = $context->readFile($file->getRealPath());
                if ($contents === '') {
                    continue;
                }
                if (!preg_match_all('/\benv\s*\(/', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($matches[0] as $m) {
                    $offset = $m[1];
                    // Skip if preceded by -> or :: (method call, not the helper)
                    $before = $offset >= 2 ? substr($contents, $offset - 2, 2) : '';
                    if ($before === '->' || $before === '::') {
                        continue;
                    }
                    $line = LineLocator::lineFromOffset($contents, $offset);
                    $findings[] = new Finding(
                        checkId: $this->id(),
                        category: $this->category(),
                        severity: Severity::MEDIUM,
                        message: 'env() llamado fuera de config/ — devolverá null tras `config:cache`.',
                        file: $context->relativePath($file->getRealPath()),
                        line: $line,
                        suggestion: 'Mueve la llamada a env() a un archivo config/*.php y usa config(\'clave\') aquí.',
                        snippet: LineLocator::snippetAround($contents, $offset),
                    );
                }
            }
        }
        return $findings;
    }
}
