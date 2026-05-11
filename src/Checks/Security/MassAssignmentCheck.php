<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Security;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;

/**
 * Detects Eloquent models that do not declare $fillable or $guarded,
 * leaving them open to mass-assignment attacks.
 */
final class MassAssignmentCheck implements Check
{
    public function id(): string
    {
        return 'security/mass-assignment';
    }

    public function category(): string
    {
        return Category::SECURITY;
    }

    public function description(): string
    {
        return 'Modelos Eloquent sin $fillable o $guarded son vulnerables a asignación masiva.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        foreach ($context->phpFiles('app/Models') as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            if (!preg_match('/extends\s+(Model|Authenticatable)\b/', $contents)) {
                continue;
            }
            // Skip abstract / trait / interface
            if (preg_match('/\babstract\s+class\b/', $contents)) {
                continue;
            }
            if (preg_match('/\$fillable\s*=/', $contents)) {
                continue;
            }
            if (preg_match('/\$guarded\s*=/', $contents)) {
                continue;
            }

            $findings[] = new Finding(
                checkId: $this->id(),
                category: $this->category(),
                severity: Severity::HIGH,
                message: 'El modelo no declara $fillable ni $guarded.',
                file: $context->relativePath($file->getRealPath()),
                line: 1,
                suggestion: 'Declara protected $fillable = [...] o protected $guarded = [] para controlar los atributos asignables masivamente.',
            );
        }

        // Also flag fallback path app/ for older projects
        if (!is_dir($context->projectRoot . '/app/Models') && is_dir($context->projectRoot . '/app')) {
            foreach ($context->phpFiles('app') as $file) {
                $contents = $context->readFile($file->getRealPath());
                if ($contents === '' || !preg_match('/extends\s+(Model|Authenticatable)\b/', $contents)) {
                    continue;
                }
                if (preg_match('/\$fillable\s*=|\$guarded\s*=/', $contents)) {
                    continue;
                }
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::HIGH,
                    message: 'El modelo no declara $fillable ni $guarded.',
                    file: $context->relativePath($file->getRealPath()),
                    line: 1,
                    suggestion: 'Declara protected $fillable = [...] o protected $guarded = [].',
                );
            }
        }

        return $findings;
    }
}
