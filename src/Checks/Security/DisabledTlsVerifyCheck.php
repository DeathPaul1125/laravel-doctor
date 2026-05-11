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
 * Flags places that disable TLS certificate verification — exposes the app to MITM attacks.
 */
final class DisabledTlsVerifyCheck implements Check
{
    public function id(): string
    {
        return 'security/disabled-tls-verify';
    }

    public function category(): string
    {
        return Category::SECURITY;
    }

    public function description(): string
    {
        return 'Desactivar la verificación TLS expone las llamadas HTTP salientes a ataques MITM.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        $patterns = [
            '/[\'"]verify[\'"]\s*=>\s*false/',
            '/CURLOPT_SSL_VERIFYPEER\s*=>\s*(?:false|0)\b/',
            '/CURLOPT_SSL_VERIFYHOST\s*=>\s*0\b/',
            '/->withoutVerifying\s*\(\s*\)/',
        ];

        foreach (['app', 'config', 'routes'] as $dir) {
            foreach ($context->phpFiles($dir) as $file) {
                $contents = $context->readFile($file->getRealPath());
                if ($contents === '') {
                    continue;
                }
                foreach ($patterns as $regex) {
                    if (preg_match_all($regex, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $m) {
                            $line = LineLocator::lineFromOffset($contents, $m[1]);
                            $findings[] = new Finding(
                                checkId: $this->id(),
                                category: $this->category(),
                                severity: Severity::HIGH,
                                message: 'Verificación TLS deshabilitada: ' . trim($m[0]),
                                file: $context->relativePath($file->getRealPath()),
                                line: $line,
                                suggestion: 'Elimina la sobreescritura o instala el bundle CA correcto. Nunca despliegues con verificación TLS deshabilitada.',
                                snippet: LineLocator::snippetAround($contents, $m[1]),
                            );
                        }
                    }
                }
            }
        }
        return $findings;
    }
}
