<?php

declare(strict_types=1);

namespace LaravelDoctor;

final class Category
{
    public const SECURITY = 'security';
    public const PERFORMANCE = 'performance';
    public const ARCHITECTURE = 'architecture';
    public const QUALITY = 'quality';

    public static function all(): array
    {
        return [self::SECURITY, self::PERFORMANCE, self::ARCHITECTURE, self::QUALITY];
    }
}
