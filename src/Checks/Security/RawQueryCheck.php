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
 * Flags raw SQL helpers that concatenate or interpolate variables, a common SQLi vector.
 *
 * Heuristic: looks for whereRaw / orderByRaw / selectRaw / DB::raw / DB::statement
 * whose argument contains string concatenation or interpolation of $variables.
 */
final class RawQueryCheck implements Check
{
    public function id(): string
    {
        return 'security/raw-query-injection';
    }

    public function category(): string
    {
        return Category::SECURITY;
    }

    public function description(): string
    {
        return 'Raw SQL with concatenated/interpolated variables risks SQL injection.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $pattern = '/\b(?:whereRaw|orderByRaw|selectRaw|havingRaw|groupByRaw|DB::raw|DB::statement|DB::select|DB::update|DB::insert|DB::delete)\s*\(\s*([^)]{1,300})\)/i';

        foreach ($context->phpFiles('app') as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $i => $arg) {
                    $argument = $arg[0];
                    $offset = $matches[0][$i][1];
                    $hasInterpolation = preg_match('/\$[a-zA-Z_]/', $argument)
                        && !preg_match('/^[\s\'"]*[^"\']*$/', $argument); // not pure string
                    $hasConcat = str_contains($argument, '.') && preg_match('/\$[a-zA-Z_]/', $argument);
                    $hasInterpolatedDoubleQuote = preg_match('/"[^"]*\$[a-zA-Z_][^"]*"/', $argument);

                    if (!$hasConcat && !$hasInterpolatedDoubleQuote && !$hasInterpolation) {
                        continue;
                    }
                    if (!$hasConcat && !$hasInterpolatedDoubleQuote) {
                        // bare $var as second arg (bindings) — likely safe
                        continue;
                    }

                    $line = LineLocator::lineFromOffset($contents, $offset);
                    $findings[] = new Finding(
                        checkId: $this->id(),
                        category: $this->category(),
                        severity: Severity::CRITICAL,
                        message: 'Raw SQL appears to interpolate or concatenate a variable.',
                        file: $context->relativePath($file->getRealPath()),
                        line: $line,
                        suggestion: 'Use parameter bindings: whereRaw("col = ?", [$value]) instead of string concatenation.',
                        snippet: LineLocator::snippetAround($contents, $offset),
                    );
                }
            }
        }
        return $findings;
    }
}
