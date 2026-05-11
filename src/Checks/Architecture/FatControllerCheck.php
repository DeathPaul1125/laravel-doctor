<?php

declare(strict_types=1);

namespace LaravelDoctor\Checks\Architecture;

use LaravelDoctor\Category;
use LaravelDoctor\Check;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;

/**
 * Flags controller files whose total LOC or longest method exceeds thresholds —
 * a strong indicator that business logic should be moved to services/actions.
 */
final class FatControllerCheck implements Check
{
    private const FILE_LOC_THRESHOLD = 250;
    private const METHOD_LOC_THRESHOLD = 60;

    public function id(): string
    {
        return 'architecture/fat-controller';
    }

    public function category(): string
    {
        return Category::ARCHITECTURE;
    }

    public function description(): string
    {
        return 'Controllers con más de ~250 líneas o métodos >60 líneas suelen tener lógica de negocio mal ubicada.';
    }

    public function run(CheckContext $context): array
    {
        $findings = [];
        foreach ($context->phpFiles('app/Http/Controllers') as $file) {
            $contents = $context->readFile($file->getRealPath());
            if ($contents === '') {
                continue;
            }
            $totalLines = substr_count($contents, "\n") + 1;
            $rel = $context->relativePath($file->getRealPath());

            if ($totalLines > self::FILE_LOC_THRESHOLD) {
                $findings[] = new Finding(
                    checkId: $this->id(),
                    category: $this->category(),
                    severity: Severity::MEDIUM,
                    message: 'El controller tiene ' . $totalLines . ' líneas (>' . self::FILE_LOC_THRESHOLD . ').',
                    file: $rel,
                    line: 1,
                    suggestion: 'Extrae la lógica de negocio a clases Service, Actions o Form Requests.',
                );
            }

            // Find methods and measure
            $tokens = @token_get_all($contents);
            if (!is_array($tokens)) {
                continue;
            }
            $i = 0;
            $count = count($tokens);
            while ($i < $count) {
                $t = $tokens[$i];
                if (is_array($t) && $t[0] === T_FUNCTION) {
                    // find name
                    $j = $i + 1;
                    while ($j < $count && (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE], true))) {
                        $j++;
                    }
                    if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $name = $tokens[$j][1];
                        $startLine = $t[2];
                        // find opening brace
                        $depth = 0;
                        $started = false;
                        $endLine = $startLine;
                        for ($k = $j; $k < $count; $k++) {
                            $tk = $tokens[$k];
                            $ch = is_array($tk) ? $tk[1] : $tk;
                            if ($ch === '{') {
                                $depth++;
                                $started = true;
                            } elseif ($ch === '}') {
                                $depth--;
                                if ($started && $depth === 0) {
                                    $endLine = is_array($tk) ? $tk[2] : $endLine;
                                    // Approximate: use line of brace via accumulating newlines is complex; rely on whitespace tokens
                                    $endLine = $this->lineOfToken($tokens, $k);
                                    $i = $k;
                                    break;
                                }
                            }
                        }
                        $methodLoc = $endLine - $startLine + 1;
                        if ($methodLoc > self::METHOD_LOC_THRESHOLD && !in_array($name, ['__construct'], true)) {
                            $findings[] = new Finding(
                                checkId: $this->id(),
                                category: $this->category(),
                                severity: Severity::MEDIUM,
                                message: 'El método ' . $name . '() tiene ' . $methodLoc . ' líneas (>' . self::METHOD_LOC_THRESHOLD . ').',
                                file: $rel,
                                line: $startLine,
                                suggestion: 'Divide el método, extrae un Service/Action, o mueve la validación a un FormRequest.',
                            );
                        }
                    }
                }
                $i++;
            }
        }
        return $findings;
    }

    private function lineOfToken(array $tokens, int $index): int
    {
        for ($k = $index; $k >= 0; $k--) {
            if (is_array($tokens[$k]) && isset($tokens[$k][2])) {
                return $tokens[$k][2];
            }
        }
        return 1;
    }
}
