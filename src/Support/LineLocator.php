<?php

declare(strict_types=1);

namespace LaravelDoctor\Support;

final class LineLocator
{
    public static function lineFromOffset(string $contents, int $offset): int
    {
        if ($offset <= 0) {
            return 1;
        }
        return substr_count(substr($contents, 0, $offset), "\n") + 1;
    }

    public static function snippetAround(string $contents, int $offset, int $maxLen = 120): string
    {
        $lines = explode("\n", $contents);
        $line = self::lineFromOffset($contents, $offset);
        $text = $lines[$line - 1] ?? '';
        $text = trim($text);
        if (strlen($text) > $maxLen) {
            $text = substr($text, 0, $maxLen - 1) . '…';
        }
        return $text;
    }
}
