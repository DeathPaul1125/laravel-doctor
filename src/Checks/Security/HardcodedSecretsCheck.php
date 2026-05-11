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
 * Flags likely hard-coded secrets in source code: passwords, API keys, tokens,
 * connection strings, and PEM blocks.
 */
final class HardcodedSecretsCheck implements Check
{
    /**
     * @var array<string, string> name => regex
     */
    private array $patterns = [
        'aws_access_key' => '/\bAKIA[0-9A-Z]{16}\b/',
        'aws_secret_key' => '/\b[A-Za-z0-9\/+=]{40}\b\s*[\'"]?\s*(?:,|;|\))/',
        'google_api_key' => '/\bAIza[0-9A-Za-z\-_]{35}\b/',
        'slack_token' => '/\bxox[abrps]-[0-9A-Za-z\-]{10,}\b/',
        'github_token' => '/\bghp_[0-9A-Za-z]{36}\b|\bgithub_pat_[0-9A-Za-z_]{20,}\b/',
        'private_key_block' => '/-----BEGIN (?:RSA |EC |OPENSSH |DSA |PGP )?PRIVATE KEY-----/',
        'jwt' => '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/',
        'bearer_token_literal' => '/[\'"]Bearer\s+[A-Za-z0-9._\-]{20,}[\'"]/',
        'password_assignment' => '/[\'"](?:password|passwd|pwd|secret|api[_-]?key|access[_-]?token)[\'"]\s*=>\s*[\'"][^\'"\s]{6,}[\'"]/i',
    ];

    public function id(): string
    {
        return 'security/hardcoded-secrets';
    }

    public function category(): string
    {
        return Category::SECURITY;
    }

    public function description(): string
    {
        return 'Strings that look like API keys, tokens, private keys or password literals.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        foreach (['app', 'config', 'routes', 'database'] as $dir) {
            foreach ($context->phpFiles($dir) as $file) {
                $rel = $context->relativePath($file->getRealPath());
                $contents = $context->readFile($file->getRealPath());
                if ($contents === '') {
                    continue;
                }
                foreach ($this->patterns as $name => $regex) {
                    if (!preg_match_all($regex, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }
                    foreach ($matches[0] as $m) {
                        $offset = $m[1];
                        $line = LineLocator::lineFromOffset($contents, $offset);
                        $snippet = LineLocator::snippetAround($contents, $offset);
                        // Skip if the line clearly comes from env() — safe.
                        if (preg_match('/\benv\s*\(/', $snippet)) {
                            continue;
                        }
                        $findings[] = new Finding(
                            checkId: $this->id(),
                            category: $this->category(),
                            severity: Severity::CRITICAL,
                            message: 'Possible hard-coded secret (' . $name . ').',
                            file: $rel,
                            line: $line,
                            suggestion: 'Move the value to .env and reference it via env() in a config file.',
                            snippet: $snippet,
                        );
                    }
                }
            }
        }
        return $findings;
    }
}
