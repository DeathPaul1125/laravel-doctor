<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Quality;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;

/**
 * Detects `use` import statements whose short name is never referenced in the file body.
 */
final class UnusedImportsCheck implements Check
{
    public function id(): string
    {
        return 'quality/unused-import';
    }

    public function category(): string
    {
        return Category::QUALITY;
    }

    public function description(): string
    {
        return 'Sentencias use no referenciadas en el cuerpo del archivo.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        foreach ($context->phpFiles('app') as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            // Match top-level use statements (skip use inside function/closures and trait `use Foo;` inside class)
            // Strategy: only look at use statements that appear before the first `class|interface|trait|enum` keyword.
            $classPos = $this->firstClassLikePos($contents);
            $headSection = $classPos === null ? $contents : substr($contents, 0, $classPos);
            $bodySection = $classPos === null ? '' : substr($contents, $classPos);

            if (!preg_match_all('/^use\s+([^;]+);/m', $headSection, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] as $m) {
                $useExpr = trim($m[0]);
                $offset = $m[1];

                // Skip function/const imports
                if (preg_match('/^(function|const)\s+/i', $useExpr)) {
                    continue;
                }
                // Handle aliases: Foo\Bar as Baz
                $shortNames = [];
                if (str_contains($useExpr, '{')) {
                    if (preg_match('/^([^{]+)\{(.+)\}$/s', $useExpr, $g)) {
                        foreach (explode(',', $g[2]) as $piece) {
                            $shortNames[] = $this->shortNameFor(trim($piece));
                        }
                    }
                } else {
                    $shortNames[] = $this->shortNameFor($useExpr);
                }

                foreach ($shortNames as $short) {
                    if ($short === '') {
                        continue;
                    }
                    if (!$this->isReferenced($bodySection, $short)) {
                        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                        $findings[] = new Finding(
                            checkId: $this->id(),
                            category: $this->category(),
                            severity: Severity::LOW,
                            message: 'Import sin usar: ' . $short,
                            file: $context->relativePath($file->getRealPath()),
                            line: $line,
                            suggestion: 'Elimina la sentencia use que no se usa.',
                        );
                    }
                }
            }
        }
        return $findings;
    }

    private function shortNameFor(string $useExpr): string
    {
        if (preg_match('/\bas\s+([A-Za-z_][A-Za-z0-9_]*)\s*$/i', $useExpr, $g)) {
            return $g[1];
        }
        $parts = explode('\\', $useExpr);
        return trim(end($parts));
    }

    private function isReferenced(string $body, string $short): bool
    {
        if ($short === '') {
            return true;
        }
        // Look for word boundaries: not preceded by alnum/_ and not followed by alnum/_, but allow ::, ->, \\, (, etc.
        $pattern = '/(?<![A-Za-z0-9_\\\\])' . preg_quote($short, '/') . '(?![A-Za-z0-9_])/';
        return (bool) preg_match($pattern, $body);
    }

    private function firstClassLikePos(string $contents): ?int
    {
        if (preg_match('/\b(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+[A-Za-z_]/', $contents, $m, PREG_OFFSET_CAPTURE)) {
            return $m[0][1];
        }
        return null;
    }
}
