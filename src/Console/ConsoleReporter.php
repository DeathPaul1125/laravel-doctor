<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Category;
use LaravelDoctor\Finding;
use LaravelDoctor\Severity;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleReporter
{
    public function __construct(private readonly OutputInterface $output)
    {
    }

    /**
     * @param array{findings: Finding[], score: int, grade: string, byCategory: array<string,int>} $result
     */
    public function render(array $result, string $projectRoot): void
    {
        $findings = $result['findings'];
        $score = $result['score'];
        $grade = $result['grade'];

        $this->output->writeln('');
        $this->output->writeln('  <fg=cyan;options=bold>Laravel Doctor</> <fg=gray>—</> ' . $projectRoot);
        $this->output->writeln('');

        if (empty($findings)) {
            $this->output->writeln('  <fg=green;options=bold>✓ No issues found.</>');
            $this->renderScore($score, $grade);
            return;
        }

        $grouped = [];
        foreach ($findings as $f) {
            $grouped[$f->category][] = $f;
        }

        foreach (Category::all() as $category) {
            if (empty($grouped[$category])) {
                continue;
            }
            $this->renderCategory($category, $grouped[$category]);
        }

        $this->renderScore($score, $grade);
        $this->renderSummaryByCategory($result['byCategory']);
        $this->renderBaselineInfo($result);
    }

    /** @param array{baselineCount?: int, knownFindings?: array} $result */
    private function renderBaselineInfo(array $result): void
    {
        if (!isset($result['baselineCount']) || $result['baselineCount'] === 0) {
            return;
        }
        $known = is_array($result['knownFindings'] ?? null) ? count($result['knownFindings']) : 0;
        $this->output->writeln(sprintf(
            '  <fg=gray>baseline: %d known issues hidden (%d still present)</>',
            $result['baselineCount'],
            $known
        ));
        $this->output->writeln('');
    }

    /** @param Finding[] $items */
    private function renderCategory(string $category, array $items): void
    {
        $title = strtoupper($category);
        $this->output->writeln(sprintf('  <fg=white;options=bold>%s</> <fg=gray>(%d)</>', $title, count($items)));
        $this->output->writeln('  <fg=gray>' . str_repeat('─', 60) . '</>');

        usort($items, fn (Finding $a, Finding $b) => Severity::weight($b->severity) <=> Severity::weight($a->severity));

        foreach ($items as $f) {
            $color = Severity::color($f->severity);
            $sev = strtoupper($f->severity);
            $location = $f->file . ($f->line ? ':' . $f->line : '');
            $this->output->writeln(sprintf('  <fg=%s;options=bold>%-8s</> <fg=gray>%s</>', $color, $sev, $f->checkId));
            $this->output->writeln('    ' . $f->message);
            $this->output->writeln('    <fg=gray>→ ' . $location . '</>');
            if ($f->snippet) {
                $this->output->writeln('    <fg=gray>  ' . trim($f->snippet) . '</>');
            }
            if ($f->suggestion) {
                $this->output->writeln('    <fg=green>💡 ' . $f->suggestion . '</>');
            }
            $this->output->writeln('');
        }
    }

    private function renderScore(int $score, string $grade): void
    {
        $color = match ($grade) {
            'great' => 'green',
            'needs-work' => 'yellow',
            'critical' => 'red',
            default => 'white',
        };
        $label = match ($grade) {
            'great' => 'GREAT',
            'needs-work' => 'NEEDS WORK',
            'critical' => 'CRITICAL',
            default => '',
        };
        $this->output->writeln('  <fg=gray>' . str_repeat('═', 60) . '</>');
        $this->output->writeln(sprintf(
            '  <options=bold>Health score:</> <fg=%s;options=bold>%d/100</> <fg=%s>(%s)</>',
            $color,
            $score,
            $color,
            $label
        ));
    }

    /** @param array<string,int> $byCategory */
    private function renderSummaryByCategory(array $byCategory): void
    {
        $parts = [];
        foreach ($byCategory as $cat => $count) {
            if ($count === 0) {
                continue;
            }
            $parts[] = sprintf('%s: %d', $cat, $count);
        }
        if (!empty($parts)) {
            $this->output->writeln('  <fg=gray>' . implode(' · ', $parts) . '</>');
        }
        $this->output->writeln('');
    }
}
