<?php

declare(strict_types=1);

namespace LaravelDoctor\Scoring;

use LaravelDoctor\Finding;
use LaravelDoctor\Severity;

final class Scorer
{
    /**
     * @param Finding[] $findings
     */
    public function score(array $findings): int
    {
        $penalty = 0;
        foreach ($findings as $f) {
            $penalty += Severity::weight($f->severity);
        }
        return max(0, min(100, 100 - $penalty));
    }

    public function grade(int $score): string
    {
        if ($score >= 75) {
            return 'great';
        }
        if ($score >= 50) {
            return 'needs-work';
        }
        return 'critical';
    }
}
