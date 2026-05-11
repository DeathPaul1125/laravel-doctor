<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Security;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;

/**
 * Reviews .env / .env.example for risky combinations: APP_DEBUG=true with APP_ENV=production,
 * empty APP_KEY, and missing .env.example.
 */
final class AppDebugCheck implements Check
{
    public function id(): string
    {
        return 'security/app-config';
    }

    public function category(): string
    {
        return Category::SECURITY;
    }

    public function description(): string
    {
        return 'Risky APP_DEBUG / APP_ENV / APP_KEY combinations in .env files.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $envPath = $context->projectRoot . '/.env';
        $examplePath = $context->projectRoot . '/.env.example';

        if (file_exists($envPath)) {
            $env = parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: [];
            $debug = strtolower((string) ($env['APP_DEBUG'] ?? ''));
            $appEnv = strtolower((string) ($env['APP_ENV'] ?? ''));
            $appKey = trim((string) ($env['APP_KEY'] ?? ''), "\"' ");

            if ($appEnv === 'production' && in_array($debug, ['true', '1', 'on'], true)) {
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::CRITICAL,
                    message: 'APP_DEBUG=true while APP_ENV=production — stack traces will leak secrets.',
                    file: '.env',
                    suggestion: 'Set APP_DEBUG=false in production environments.',
                );
            }
            if ($appKey === '' || $appKey === 'SomeRandomString') {
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::CRITICAL,
                    message: 'APP_KEY is empty or placeholder — encryption, sessions and signed URLs will be insecure.',
                    file: '.env',
                    suggestion: 'Run `php artisan key:generate` to create a strong APP_KEY.',
                );
            }
        }

        if (file_exists($envPath) && !file_exists($examplePath)) {
            $findings[] = new Finding(
                checkId: $this->id(),
                category: $this->category(),
                severity: Severity::LOW,
                message: '.env.example is missing — onboarding new contributors will be harder.',
                file: '.env.example',
                suggestion: 'Commit a sanitized .env.example with no secrets.',
            );
        }

        return $findings;
    }
}
