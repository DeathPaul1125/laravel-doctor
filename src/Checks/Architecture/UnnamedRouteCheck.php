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
 * Flags Route::get/post/put/patch/delete/any/match definitions that are not chained with ->name().
 * Named routes are required to use route(...) helpers and prevent broken links.
 */
final class UnnamedRouteCheck implements Check
{
    public function id(): string
    {
        return 'architecture/unnamed-route';
    }

    public function category(): string
    {
        return Category::ARCHITECTURE;
    }

    public function description(): string
    {
        return 'Routes without ->name() cannot be referenced by route() and tend to break silently.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $dir = $context->projectRoot . '/routes';
        if (!is_dir($dir)) {
            return $findings;
        }

        $finder = (new Finder())->files()->in($dir)->name('*.php');
        $pattern = '/Route::(get|post|put|patch|delete|any|match)\s*\(/';

        foreach ($finder as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            if (!preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as $m) {
                $offset = $m[1];
                $statement = $this->extractStatement($contents, $offset);
                if ($statement === null) {
                    continue;
                }
                if (str_contains($statement, '->name(')) {
                    continue;
                }
                // Skip resource/apiResource — they auto-name
                if (preg_match('/Route::(resource|apiResource)/', substr($contents, $offset, 30))) {
                    continue;
                }
                $line = LineLocator::lineFromOffset($contents, $offset);
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::LOW,
                    message: 'Route definition without ->name().',
                    file: $context->relativePath($file->getRealPath()),
                    line: $line,
                    suggestion: 'Append ->name(\'descriptive.name\') so route() / signed URLs work.',
                    snippet: LineLocator::snippetAround($contents, $offset),
                );
            }
        }
        return $findings;
    }

    private function extractStatement(string $contents, int $offset): ?string
    {
        // Capture up to ; from $offset
        $end = strpos($contents, ';', $offset);
        if ($end === false) {
            return null;
        }
        return substr($contents, $offset, $end - $offset);
    }
}
