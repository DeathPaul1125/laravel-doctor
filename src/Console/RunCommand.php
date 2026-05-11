<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Baseline;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Config;
use LaravelDoctor\Doctor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RunCommand extends Command
{
    protected static $defaultName = 'run';
    protected static $defaultDescription = 'Diagnose a Laravel project for security, performance, architecture and quality issues.';

    protected function configure(): void
    {
        $this
            ->setName('run')
            ->setDescription('Diagnose a Laravel project for security, performance, architecture and quality issues.')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to the Laravel project root.', '.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON instead of human output.')
            ->addOption('min-score', null, InputOption::VALUE_REQUIRED, 'Fail (exit 1) when the health score is below this threshold.', '0')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Run only checks of a given category (security|performance|architecture|quality).')
            ->addOption('no-progress', null, InputOption::VALUE_NONE, 'Disable the progress indicator (auto-disabled with --json).')
            ->addOption('baseline', null, InputOption::VALUE_NONE, 'Use .laravel-doctor.baseline.json — only new findings count.')
            ->addOption('update-baseline', null, InputOption::VALUE_NONE, 'Write the current findings into the baseline file and exit.')
            ->addOption('html', null, InputOption::VALUE_REQUIRED, 'Write an interactive HTML report to the given path.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        $absolute = realpath($path);
        if ($absolute === false || !is_dir($absolute)) {
            $output->writeln('<error>Path not found: ' . $path . '</error>');
            return Command::FAILURE;
        }

        $context = new CheckContext($absolute);
        if (!$context->isLaravelProject()) {
            $output->writeln('<comment>Warning: ' . $absolute . ' does not look like a Laravel project (missing artisan or composer.json). Continuing anyway.</comment>');
        }

        $config = Config::loadFromProject($absolute);
        $checks = Doctor::defaultChecks();
        $category = $input->getOption('category');
        if ($category) {
            $checks = array_values(array_filter($checks, fn ($c) => $c->category() === $category));
            if (empty($checks)) {
                $output->writeln('<error>Unknown category: ' . $category . '</error>');
                return Command::FAILURE;
            }
        }

        $isJson = (bool) $input->getOption('json');
        $useBaseline = (bool) $input->getOption('baseline');
        $updateBaseline = (bool) $input->getOption('update-baseline');
        $showProgress = !$isJson && !(bool) $input->getOption('no-progress');

        if ($showProgress) {
            $output->writeln('');
            $output->writeln('  <fg=cyan>Running ' . count($checks) . ' checks…</>');
        }

        $doctor = new Doctor($checks);
        $baseline = ($useBaseline && !$updateBaseline) ? Baseline::load($absolute) : null;

        $result = $doctor->diagnose(
            $context,
            $showProgress
                ? function ($check) use ($output) {
                    $output->writeln('  <fg=gray>· ' . $check->id() . '</>');
                }
                : null,
            $config,
            $baseline,
        );

        if ($updateBaseline) {
            // Use all findings (ignoring baseline) so we capture the full snapshot.
            $allResult = $doctor->diagnose($context, null, $config, null);
            $count = Baseline::write($absolute, $allResult['findings']);
            $output->writeln('');
            $output->writeln('  <fg=green>✓ Baseline updated: ' . $count . ' findings recorded.</>');
            $output->writeln('  <fg=gray>→ ' . Baseline::path($absolute) . '</>');
            return Command::SUCCESS;
        }

        $htmlPath = $input->getOption('html');
        if ($htmlPath) {
            $htmlPath = $this->resolveHtmlPath((string) $htmlPath, $absolute);
            (new HtmlReporter())->render($result, $absolute, $htmlPath);
            $output->writeln('');
            $output->writeln('  <fg=green>✓ HTML report written:</> ' . $htmlPath);
        }

        if ($isJson) {
            (new JsonReporter($output))->render($result, $context->relativePath($absolute) ?: $absolute);
        } else {
            (new ConsoleReporter($output))->render($result, $absolute);
        }

        $minScore = (int) $input->getOption('min-score');
        if ($result['score'] < $minScore) {
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    private function resolveHtmlPath(string $path, string $projectRoot): string
    {
        if ($path === '' || preg_match('#^[a-zA-Z]:[\\\\/]#', $path) || str_starts_with($path, '/')) {
            return $path;
        }
        return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . $path;
    }
}
