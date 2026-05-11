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
 * Flags `Model::all()` outside model files (usually a perf bomb on large tables)
 * and command/job classes that use ->get() without pagination/chunk.
 */
final class AllVsChunkCheck implements Check
{
    public function id(): string
    {
        return 'performance/load-all-rows';
    }

    public function category(): string
    {
        return Category::PERFORMANCE;
    }

    public function description(): string
    {
        return 'Model::all() / unbounded ->get() may load the whole table into memory.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        foreach ($context->phpFiles('app') as $file) {
            $rel = $context->relativePath($file->getRealPath());
            // Skip Models — Model::all() inside the model itself is uncommon and rarely the perf issue.
            if (str_starts_with($rel, 'app/Models')) {
                continue;
            }
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            if (preg_match_all('/\b([A-Z][A-Za-z0-9_]*)::all\s*\(\s*\)/', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $i => $m) {
                    $line = LineLocator::lineFromOffset($contents, $m[1]);
                    $findings[] = new Finding(
                        checkId: $this->id(),
                        category: $this->category(),
                        severity: Severity::MEDIUM,
                        message: $matches[1][$i][0] . '::all() loads the entire table into memory.',
                        file: $rel,
                        line: $line,
                        suggestion: 'Use ->chunk(N, ...) / lazy() / cursor() / paginate() depending on use case.',
                        snippet: LineLocator::snippetAround($contents, $m[1]),
                    );
                }
            }
        }
        return $findings;
    }
}
