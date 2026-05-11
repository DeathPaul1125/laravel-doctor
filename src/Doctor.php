<?php

declare(strict_types=1);

namespace LaravelDoctor;

use LaravelDoctor\Checks\Architecture\ClosureRouteCheck;
use LaravelDoctor\Checks\Architecture\FatControllerCheck;
use LaravelDoctor\Checks\Architecture\MigrationDownCheck;
use LaravelDoctor\Checks\Architecture\UnnamedRouteCheck;
use LaravelDoctor\Checks\Architecture\ValidationInControllerCheck;
use LaravelDoctor\Checks\Performance\AllVsChunkCheck;
use LaravelDoctor\Checks\Performance\MissingForeignIndexCheck;
use LaravelDoctor\Checks\Performance\PossibleNPlusOneCheck;
use LaravelDoctor\Checks\Quality\DebugStatementsCheck;
use LaravelDoctor\Checks\Quality\DuplicateMigrationCheck;
use LaravelDoctor\Checks\Quality\UnusedImportsCheck;
use LaravelDoctor\Checks\Security\AppDebugCheck;
use LaravelDoctor\Checks\Security\BladeUnescapedCheck;
use LaravelDoctor\Checks\Security\DisabledTlsVerifyCheck;
use LaravelDoctor\Checks\Security\EnvOutsideConfigCheck;
use LaravelDoctor\Checks\Security\HardcodedSecretsCheck;
use LaravelDoctor\Checks\Security\MassAssignmentCheck;
use LaravelDoctor\Checks\Security\MissingCsrfCheck;
use LaravelDoctor\Checks\Security\RawQueryCheck;
use LaravelDoctor\Checks\Security\RequestAllToFillCheck;
use LaravelDoctor\Scoring\Scorer;

final class Doctor
{
    /** @var Check[] */
    private array $checks;

    public function __construct(?array $checks = null)
    {
        $this->checks = $checks ?? self::defaultChecks();
    }

    /**
     * @return Check[]
     */
    public static function defaultChecks(): array
    {
        return [
            // security
            new MassAssignmentCheck(),
            new RawQueryCheck(),
            new BladeUnescapedCheck(),
            new AppDebugCheck(),
            new EnvOutsideConfigCheck(),
            new HardcodedSecretsCheck(),
            new MissingCsrfCheck(),
            new RequestAllToFillCheck(),
            new DisabledTlsVerifyCheck(),
            // performance
            new PossibleNPlusOneCheck(),
            new AllVsChunkCheck(),
            new MissingForeignIndexCheck(),
            // architecture
            new FatControllerCheck(),
            new ValidationInControllerCheck(),
            new UnnamedRouteCheck(),
            new ClosureRouteCheck(),
            new MigrationDownCheck(),
            // quality
            new UnusedImportsCheck(),
            new DuplicateMigrationCheck(),
            new DebugStatementsCheck(),
        ];
    }

    /**
     * @return array{
     *   findings: Finding[],
     *   newFindings: Finding[],
     *   knownFindings: Finding[],
     *   score: int,
     *   grade: string,
     *   byCategory: array<string, int>,
     *   baselineCount: int
     * }
     */
    public function diagnose(
        CheckContext $context,
        ?callable $onCheckStart = null,
        ?Config $config = null,
        ?Baseline $baseline = null,
    ): array {
        $config ??= new Config();
        $findings = [];
        foreach ($this->checks as $check) {
            if ($config->isCheckDisabled($check->id())) {
                continue;
            }
            if ($onCheckStart) {
                $onCheckStart($check);
            }
            foreach ($check->run($context) as $finding) {
                if ($config->isFindingIgnored($finding)) {
                    continue;
                }
                $findings[] = $finding;
            }
        }

        $newFindings = $findings;
        $knownFindings = [];
        $baselineCount = 0;
        if ($baseline !== null) {
            $baselineCount = $baseline->count();
            $newFindings = [];
            foreach ($findings as $f) {
                if ($baseline->isKnown($f)) {
                    $knownFindings[] = $f;
                } else {
                    $newFindings[] = $f;
                }
            }
        }

        $scorer = new Scorer();
        // Score is computed on findings that still count (new ones when baseline applied, all otherwise).
        $forScore = $baseline === null ? $findings : $newFindings;
        $score = $scorer->score($forScore);

        $byCategory = array_fill_keys(Category::all(), 0);
        foreach ($forScore as $f) {
            $byCategory[$f->category] = ($byCategory[$f->category] ?? 0) + 1;
        }

        return [
            'findings' => $forScore,
            'newFindings' => $newFindings,
            'knownFindings' => $knownFindings,
            'score' => $score,
            'grade' => $scorer->grade($score),
            'byCategory' => $byCategory,
            'baselineCount' => $baselineCount,
        ];
    }

    /** @return Check[] */
    public function checks(): array
    {
        return $this->checks;
    }
}
