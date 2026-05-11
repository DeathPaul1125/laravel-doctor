<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Performance;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;
use LaravelDoctor\Support\LineLocator;

/**
 * Heuristic for N+1: detects @foreach in Blade and `foreach` in PHP whose body accesses
 * a relation property/method on the loop variable (a strong N+1 smell when no `->load`
 * or `with()` precedes it).
 */
final class PossibleNPlusOneCheck implements Check
{
    public function id(): string
    {
        return 'performance/possible-n-plus-one';
    }

    public function category(): string
    {
        return Category::PERFORMANCE;
    }

    public function description(): string
    {
        return 'Loops that lazy-load relations on each iteration cause N+1 queries.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];

        // Blade: @foreach ($items as $item) ... $item->relation->...
        foreach ($context->bladeFiles() as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            if (preg_match_all('/@foreach\s*\(\s*\$([a-zA-Z_][a-zA-Z0-9_]*)\s+as\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\)(.*?)@endforeach/s', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $i => $whole) {
                    $itemVar = $matches[2][$i][0];
                    $body = $matches[3][$i][0];
                    // Look for $item->something->something (chained relation access)
                    if (preg_match('/\$' . preg_quote($itemVar, '/') . '->[a-zA-Z_][a-zA-Z0-9_]*->[a-zA-Z_]/', $body)) {
                        $line = LineLocator::lineFromOffset($contents, $whole[1]);
                        $findings[] = new Finding(
                            checkId: $this->id(),
                            category: $this->category(),
                            severity: Severity::MEDIUM,
                            message: 'Possible N+1 in @foreach: chained relation access on $' . $itemVar . ' inside the loop.',
                            file: $context->relativePath($file->getRealPath()),
                            line: $line,
                            suggestion: 'Eager-load with ->with([\'relation\']) on the source query, or pre-load via $collection->load(...).',
                        );
                    }
                }
            }
        }

        // PHP: foreach ($items as $item) { ...$item->relation->... }
        foreach ($context->phpFiles('app') as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            if (preg_match_all('/foreach\s*\(\s*\$([a-zA-Z_][a-zA-Z0-9_]*)\s+as\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\)\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $i => $whole) {
                    $itemVar = $matches[2][$i][0];
                    $body = $matches[3][$i][0];
                    if (preg_match('/\$' . preg_quote($itemVar, '/') . '->[a-zA-Z_][a-zA-Z0-9_]*->[a-zA-Z_]/', $body)
                        || preg_match('/\$' . preg_quote($itemVar, '/') . '->[a-zA-Z_]+\(\)->/', $body)) {
                        $line = LineLocator::lineFromOffset($contents, $whole[1]);
                        $findings[] = new Finding(
                            checkId: $this->id(),
                            category: $this->category(),
                            severity: Severity::MEDIUM,
                            message: 'Possible N+1: chained relation access on $' . $itemVar . ' inside foreach.',
                            file: $context->relativePath($file->getRealPath()),
                            line: $line,
                            suggestion: 'Eager-load the relation with ->with([...]) or call ->load([...]) before the loop.',
                        );
                    }
                }
            }
        }

        return $findings;
    }
}
