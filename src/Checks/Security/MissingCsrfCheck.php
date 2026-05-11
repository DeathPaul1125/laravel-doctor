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
 * Flags Blade <form> tags with non-GET methods that don't include @csrf / csrf_field().
 */
final class MissingCsrfCheck implements Check
{
    public function id(): string
    {
        return 'security/missing-csrf';
    }

    public function category(): string
    {
        return Category::SECURITY;
    }

    public function description(): string
    {
        return 'Blade forms with method != GET should include @csrf to prevent CSRF attacks.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        foreach ($context->bladeFiles() as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '' || stripos($contents, '<form') === false) {
                continue;
            }
            $pattern = '/<form\b[^>]*?method\s*=\s*[\'"](?P<method>[a-zA-Z]+)[\'"][^>]*>(?P<body>.*?)<\/form>/is';
            if (!preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as $i => $whole) {
                $method = strtolower($matches['method'][$i][0]);
                $body = $matches['body'][$i][0];
                if ($method === 'get') {
                    continue;
                }
                if (str_contains($body, '@csrf') || str_contains($body, 'csrf_field(') || str_contains($body, 'csrf-token')) {
                    continue;
                }
                $line = LineLocator::lineFromOffset($contents, $whole[1]);
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::HIGH,
                    message: '<form method="' . strtoupper($method) . '"> without @csrf — endpoint is vulnerable to CSRF.',
                    file: $context->relativePath($file->getRealPath()),
                    line: $line,
                    suggestion: 'Add @csrf as the first child of the <form>.',
                );
            }
        }
        return $findings;
    }
}
