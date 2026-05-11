<?php

declare(strict_types=1);

namespace LaravelDoctor;

/**
 * Project-level configuration loaded from .laravel-doctor.json at the project root.
 *
 * Example:
 * {
 *   "disable": ["architecture/unnamed-route"],
 *   "ignore": [
 *     "app/Http/Controllers/Legacy/**",
 *     {"path": "app/Models/User.php", "check": "security/mass-assignment"}
 *   ],
 *   "thresholds": { "fatController": { "fileLoc": 300, "methodLoc": 80 } }
 * }
 */
final class Config
{
    /**
     * @param string[] $disabledChecks
     * @param array<int, string|array{path?:string,check?:string,line?:int}> $ignore
     * @param array<string, mixed> $thresholds
     */
    public function __construct(
        public readonly array $disabledChecks = [],
        public readonly array $ignore = [],
        public readonly array $thresholds = [],
    ) {
    }

    public static function loadFromProject(string $projectRoot): self
    {
        $path = $projectRoot . DIRECTORY_SEPARATOR . '.laravel-doctor.json';
        if (!file_exists($path)) {
            return new self();
        }
        $raw = @file_get_contents($path);
        if (!$raw) {
            return new self();
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return new self();
        }
        return new self(
            disabledChecks: is_array($data['disable'] ?? null) ? array_values(array_filter($data['disable'], 'is_string')) : [],
            ignore: is_array($data['ignore'] ?? null) ? $data['ignore'] : [],
            thresholds: is_array($data['thresholds'] ?? null) ? $data['thresholds'] : [],
        );
    }

    public function isCheckDisabled(string $checkId): bool
    {
        return in_array($checkId, $this->disabledChecks, true);
    }

    public function isFindingIgnored(Finding $f): bool
    {
        foreach ($this->ignore as $entry) {
            if (is_string($entry)) {
                if ($this->pathMatches($entry, $f->file)) {
                    return true;
                }
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }
            $matchPath = !isset($entry['path']) || $this->pathMatches((string) $entry['path'], $f->file);
            $matchCheck = !isset($entry['check']) || $entry['check'] === $f->checkId;
            $matchLine = !isset($entry['line']) || (int) $entry['line'] === $f->line;
            if ($matchPath && $matchCheck && $matchLine) {
                return true;
            }
        }
        return false;
    }

    /**
     * Simple glob-style match: ** for any depth, * for single segment.
     */
    private function pathMatches(string $pattern, string $path): bool
    {
        $regex = '#^' . str_replace(
            ['\*\*/', '\*\*', '\*', '\?'],
            ['(?:.*/)?', '.*', '[^/]*', '.'],
            preg_quote($pattern, '#')
        ) . '$#';
        return (bool) preg_match($regex, $path);
    }
}
