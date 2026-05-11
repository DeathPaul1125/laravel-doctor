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
 * Flags {!! $var !!} usage in Blade templates — these are unescaped and risk XSS.
 */
final class BladeUnescapedCheck implements Check
{
    public function id(): string
    {
        return 'security/blade-unescaped';
    }

    public function category(): string
    {
        return Category::SECURITY;
    }

    public function description(): string
    {
        return 'Blade {!! !!} bypasses HTML escaping and can lead to XSS.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        foreach ($context->bladeFiles() as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            if (preg_match_all('/\{!!\s*(.+?)\s*!!\}/s', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $i => $m) {
                    $expr = trim($matches[1][$i][0]);
                    // Skip whitelisted safe helpers (commonly trusted HTML producers)
                    if (preg_match('/^(\$errors->|csrf_field|method_field|@?html\(|Form::|\$slot\b)/', $expr)) {
                        continue;
                    }
                    $line = LineLocator::lineFromOffset($contents, $m[1]);
                    $findings[] = new Finding(
                        checkId: $this->id(),
                        category: $this->category(),
                        severity: Severity::HIGH,
                        message: 'Unescaped Blade output: {!! ' . self::truncate($expr) . ' !!}',
                        file: $context->relativePath($file->getRealPath()),
                        line: $line,
                        suggestion: 'Use {{ $var }} to escape output, or wrap trusted HTML in a dedicated component/cast.',
                    );
                }
            }
        }
        return $findings;
    }

    private static function truncate(string $s): string
    {
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return strlen($s) > 60 ? substr($s, 0, 60) . '…' : $s;
    }
}
