<?php

declare(strict_types=1);

namespace LaravelDoctor;

final class Severity
{
    public const CRITICAL = 'critical';
    public const HIGH = 'high';
    public const MEDIUM = 'medium';
    public const LOW = 'low';

    public static function weight(string $severity): int
    {
        return match ($severity) {
            self::CRITICAL => 10,
            self::HIGH => 5,
            self::MEDIUM => 2,
            self::LOW => 1,
            default => 1,
        };
    }

    public static function color(string $severity): string
    {
        return match ($severity) {
            self::CRITICAL => 'red',
            self::HIGH => 'red',
            self::MEDIUM => 'yellow',
            self::LOW => 'cyan',
            default => 'default',
        };
    }
}
