<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Architecture;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;
use LaravelDoctor\Support\LineLocator;
use Symfony\Component\Finder\Finder;

/**
 * Routes that use a closure as the action cannot be cached by `route:cache`.
 * For real apps this is a deploy-time perf hit and an obstacle to controller-based testing.
 */
final class ClosureRouteCheck implements Check
{
    public function id(): string
    {
        return 'architecture/closure-route';
    }

    public function category(): string
    {
        return Category::ARCHITECTURE;
    }

    public function description(): string
    {
        return 'Closure-based routes cannot be cached by `php artisan route:cache`.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $dir = $context->projectRoot . '/routes';
        if (!is_dir($dir)) {
            return $findings;
        }
        $finder = (new Finder())->files()->in($dir)->name('*.php');
        $pattern = '/Route::(?:get|post|put|patch|delete|any|match)\s*\([^;]*?(?:function\s*\([^)]*\)\s*(?:use\s*\([^)]*\)\s*)?\{|fn\s*\([^)]*\)\s*=>)/s';

        foreach ($finder as $file) {
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
                        severity: Severity::LOW,
                        message: 'Route uses a closure — prevents `route:cache`.',
                        file: $context->relativePath($file->getRealPath()),
                        line: $line,
                        suggestion: 'Move the action to a controller (e.g. [SomeController::class, \'index\']).',
                        snippet: LineLocator::snippetAround($contents, $m[1]),
                    );
                }
            }
        }
        return $findings;
    }
}
