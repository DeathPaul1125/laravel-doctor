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
 * Flags Model::create / ::update / ->fill / ->update receiving $request->all() or request()->all().
 * This bypasses input filtering and trusts the entire request — a classic mass-assignment vector.
 */
final class RequestAllToFillCheck implements Check
{
    public function id(): string
    {
        return 'security/request-all-to-fill';
    }

    public function category(): string
    {
        return Category::SECURITY;
    }

    public function description(): string
    {
        return 'Passing $request->all() into create()/update()/fill() bypasses input filtering.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $pattern = '/\b(?:create|update|fill|insert|forceCreate|firstOrCreate|updateOrCreate)\s*\(\s*(?:\$request->all\(\)|request\(\)->all\(\))/';

        foreach ($context->phpFiles('app') as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $m) {
                    $line = LineLocator::lineFromOffset($contents, $m[1]);
                    $findings[] = new Finding(
                        checkId: $this->id(),
                        category: $this->category(),
                        severity: Severity::HIGH,
                        message: 'Mass assignment from $request->all(): ' . trim($m[0]),
                        file: $context->relativePath($file->getRealPath()),
                        line: $line,
                        suggestion: 'Use $request->validated() (FormRequest) or $request->only([...]) to filter input.',
                        snippet: LineLocator::snippetAround($contents, $m[1]),
                    );
                }
            }
        }
        return $findings;
    }
}
