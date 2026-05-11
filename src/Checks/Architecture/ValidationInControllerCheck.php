<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Architecture;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;
use LaravelDoctor\Support\LineLocator;

/**
 * Encourages use of FormRequest classes instead of inline validation in controllers.
 */
final class ValidationInControllerCheck implements Check
{
    public function id(): string
    {
        return 'architecture/validation-in-controller';
    }

    public function category(): string
    {
        return Category::ARCHITECTURE;
    }

    public function description(): string
    {
        return 'Controllers performing $request->validate() / Validator::make inline would be cleaner as FormRequests.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $pattern = '/(\$request->validate\s*\(|\bValidator::make\s*\(|\$this->validate\s*\()/';

        foreach ($context->phpFiles('app/Http/Controllers') as $file) {
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
                        message: 'Inline validation in controller (' . trim($m[0], '(') . ').',
                        file: $context->relativePath($file->getRealPath()),
                        line: $line,
                        suggestion: 'Create a FormRequest with `php artisan make:request` and type-hint it on the action.',
                    );
                }
            }
        }
        return $findings;
    }
}
