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
        return 'Combinaciones riesgosas de APP_DEBUG / APP_ENV / APP_KEY en .env.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $envPath = $context->projectRoot . '/.env';
        $examplePath = $context->projectRoot . '/.env.example';

        if (file_exists($envPath)) {
            $env = @parse_ini_file($envPath, false, INI_SCANNER_RAW);
            if (!is_array($env)) {
                $env = $this->parseEnvFallback($envPath);
            }
            $debug = strtolower((string) ($env['APP_DEBUG'] ?? ''));
            $appEnv = strtolower((string) ($env['APP_ENV'] ?? ''));
            $appKey = trim((string) ($env['APP_KEY'] ?? ''), "\"' ");

            if ($appEnv === 'production' && in_array($debug, ['true', '1', 'on'], true)) {
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::CRITICAL,
                    message: 'APP_DEBUG=true con APP_ENV=production — los stack traces filtrarán secretos.',
                    file: '.env',
                    suggestion: 'Pon APP_DEBUG=false en ambientes productivos.',
                );
            }
            if ($appKey === '' || $appKey === 'SomeRandomString') {
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::CRITICAL,
                    message: 'APP_KEY está vacío o tiene un valor placeholder — encriptación, sesiones y URLs firmadas serán inseguras.',
                    file: '.env',
                    suggestion: 'Ejecuta `php artisan key:generate` para generar un APP_KEY robusto.',
                );
            }
        }

        if (file_exists($envPath) && !file_exists($examplePath)) {
            $findings[] = new Finding(
                checkId: $this->id(),
                category: $this->category(),
                severity: Severity::LOW,
                message: 'Falta .env.example — dificulta el onboarding de nuevos colaboradores.',
                file: '.env.example',
                suggestion: 'Sube al repo un .env.example saneado, sin secretos.',
            );
        }

        return $findings;
    }

    /**
     * Parser .env tolerante (cuando parse_ini_file falla por comentarios con caracteres especiales).
     *
     * @return array<string, string>
     */
    private function parseEnvFallback(string $path): array
    {
        $env = [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = ltrim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            $env[$key] = $value;
        }
        return $env;
    }
}
