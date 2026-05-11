<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Finding;
use Symfony\Component\Console\Output\OutputInterface;

final class JsonReporter
{
    public function __construct(private readonly OutputInterface $output)
    {
    }

    /**
     * @param array{findings: Finding[], score: int, grade: string, byCategory: array<string,int>} $result
     */
    public function render(array $result, string $projectRoot): void
    {
        $payload = [
            'project' => $projectRoot,
            'score' => $result['score'],
            'grade' => $result['grade'],
            'totals' => [
                'findings' => count($result['findings']),
                'byCategory' => $result['byCategory'],
                'baselineKnown' => $result['baselineCount'] ?? 0,
            ],
            'findings' => array_map(fn (Finding $f) => $f->toArray(), $result['findings']),
        ];
        $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
