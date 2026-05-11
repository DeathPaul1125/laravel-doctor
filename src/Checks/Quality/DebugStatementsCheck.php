<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Quality;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;
use LaravelDoctor\Support\LineLocator;

/**
 * Flags leftover debug calls: dd(), dump(), var_dump(), print_r(), ray(), Log::debug() in non-debug code.
 */
final class DebugStatementsCheck implements Check
{
    public function id(): string
    {
        return 'quality/debug-statements';
    }

    public function category(): string
    {
        return Category::QUALITY;
    }

    public function description(): string
    {
        return 'Llamadas de debug olvidadas (dd, dump, var_dump, print_r, ray) en el código.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $pattern = '/(?<![A-Za-z0-9_>])(dd|dump|var_dump|print_r|ray)\s*\(/';
        foreach (['app', 'routes'] as $dir) {
            foreach ($context->phpFiles($dir) as $file) {
                $contents = $context->readFile($file->getRealPath());
                if ($contents === '') {
                    continue;
                }
                $rel = $context->relativePath($file->getRealPath());
                if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[1] as $i => $name) {
                        $offset = $matches[0][$i][1];
                        $line = LineLocator::lineFromOffset($contents, $offset);
                        $snippet = LineLocator::snippetAround($contents, $offset);
                        // Skip lines that are commented out
                        if (preg_match('/^\s*(?:\/\/|#|\*)/', $snippet)) {
                            continue;
                        }
                        $findings[] = new Finding(
                            checkId: $this->id(),
                            category: $this->category(),
                            severity: Severity::MEDIUM,
                            message: 'Sentencia de debug: ' . $name[0] . '(...)',
                            file: $rel,
                            line: $line,
                            suggestion: 'Elimina la llamada de debug antes de desplegar; usa Log::debug() si realmente la necesitas.',
                            snippet: $snippet,
                        );
                    }
                }
            }
        }
        return $findings;
    }
}
