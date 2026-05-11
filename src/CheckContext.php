<?php

declare(strict_types=1);

namespace LaravelDoctor;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class CheckContext
{
    /** @var array<string, SplFileInfo[]> */
    private array $cache = [];

    public function __construct(
        public readonly string $projectRoot,
    ) {
    }

    public function isLaravelProject(): bool
    {
        return file_exists($this->projectRoot . '/artisan')
            && file_exists($this->projectRoot . '/composer.json');
    }

    /**
     * @return SplFileInfo[]
     */
    public function phpFiles(string $relativePath = ''): array
    {
        $key = 'php:' . $relativePath;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $path = $this->projectRoot . ($relativePath ? '/' . ltrim($relativePath, '/') : '');
        if (!is_dir($path)) {
            return $this->cache[$key] = [];
        }

        $finder = (new Finder())
            ->files()
            ->in($path)
            ->name('*.php')
            ->exclude(['vendor', 'node_modules', 'storage', 'bootstrap/cache', '.git']);

        return $this->cache[$key] = iterator_to_array($finder, false);
    }

    /**
     * @return SplFileInfo[]
     */
    public function bladeFiles(): array
    {
        if (isset($this->cache['blade'])) {
            return $this->cache['blade'];
        }
        $path = $this->projectRoot . '/resources/views';
        if (!is_dir($path)) {
            return $this->cache['blade'] = [];
        }
        $finder = (new Finder())->files()->in($path)->name('*.blade.php');
        return $this->cache['blade'] = iterator_to_array($finder, false);
    }

    public function readFile(string $absolutePath): string
    {
        return @file_get_contents($absolutePath) ?: '';
    }

    public function relativePath(string $absolutePath): string
    {
        $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/');
        $path = str_replace('\\', '/', $absolutePath);
        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }
        return $path;
    }
}
