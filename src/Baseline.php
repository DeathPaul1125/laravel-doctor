<?php

declare(strict_types=1);

namespace LaravelDoctor;

/**
 * Tracks the set of findings considered "known/accepted" so future runs only highlight new issues.
 * Stored at .laravel-doctor.baseline.json at the project root.
 */
final class Baseline
{
    /** @var array<string, bool> */
    private array $hashes;

    /**
     * @param array<string, bool> $hashes
     */
    private function __construct(array $hashes)
    {
        $this->hashes = $hashes;
    }

    public static function path(string $projectRoot): string
    {
        return $projectRoot . DIRECTORY_SEPARATOR . '.laravel-doctor.baseline.json';
    }

    public static function load(string $projectRoot): self
    {
        $path = self::path($projectRoot);
        if (!file_exists($path)) {
            return new self([]);
        }
        $data = json_decode((string) @file_get_contents($path), true);
        if (!is_array($data) || !is_array($data['hashes'] ?? null)) {
            return new self([]);
        }
        $hashes = [];
        foreach ($data['hashes'] as $h) {
            if (is_string($h)) {
                $hashes[$h] = true;
            }
        }
        return new self($hashes);
    }

    /**
     * @param Finding[] $findings
     */
    public static function write(string $projectRoot, array $findings): int
    {
        $hashes = [];
        foreach ($findings as $f) {
            $hashes[] = self::hash($f);
        }
        $hashes = array_values(array_unique($hashes));
        sort($hashes);
        $payload = [
            'generated' => date(DATE_ATOM),
            'count' => count($hashes),
            'hashes' => $hashes,
        ];
        $written = @file_put_contents(self::path($projectRoot), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $written === false ? 0 : count($hashes);
    }

    public function isKnown(Finding $f): bool
    {
        return isset($this->hashes[self::hash($f)]);
    }

    public function count(): int
    {
        return count($this->hashes);
    }

    public static function hash(Finding $f): string
    {
        return substr(sha1(implode('|', [$f->checkId, $f->file, (string) $f->line, $f->message])), 0, 16);
    }
}
