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
            ->setDescription('Diagnostica un proyecto Laravel: seguridad, rendimiento, arquitectura y calidad.')
            ->addArgument('path', InputArgument::OPTIONAL, 'Ruta a la raíz del proyecto Laravel.', '.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emite JSON en lugar de salida legible.')
            ->addOption('min-score', null, InputOption::VALUE_REQUIRED, 'Falla (exit 1) cuando la puntuación es menor a este umbral.', '0')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Ejecuta solo los checks de una categoría (security|performance|architecture|quality).')
            ->addOption('no-progress', null, InputOption::VALUE_NONE, 'Desactiva el indicador de progreso (se desactiva solo con --json).')
            ->addOption('baseline', null, InputOption::VALUE_NONE, 'Usa .laravel-doctor.baseline.json — solo cuentan los hallazgos nuevos.')
            ->addOption('update-baseline', null, InputOption::VALUE_NONE, 'Guarda los hallazgos actuales en el archivo baseline y sale.')
            ->addOption('html', null, InputOption::VALUE_REQUIRED, 'Genera un reporte HTML interactivo en la ruta indicada.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('path');
        $absolute = realpath($path);
        if ($absolute === false || !is_dir($absolute)) {
            $output->writeln('<error>Ruta no encontrada: ' . $path . '</error>');
            return Command::FAILURE;
        }

        $context = new CheckContext($absolute);
        if (!$context->isLaravelProject()) {
            $output->writeln('<comment>Aviso: ' . $absolute . ' no parece ser un proyecto Laravel (falta artisan o composer.json). Se continúa de todos modos.</comment>');
        }

        $config = Config::loadFromProject($absolute);
        $checks = Doctor::defaultChecks();
        $category = $input->getOption('category');
        if ($category) {
            $checks = array_values(array_filter($checks, fn ($c) => $c->category() === $category));
            if (empty($checks)) {
                $output->writeln('<error>Categoría desconocida: ' . $category . '</error>');
                return Command::FAILURE;
            }
        }

        $isJson = (bool) $input->getOption('json');
        $useBaseline = (bool) $input->getOption('baseline');
        $updateBaseline = (bool) $input->getOption('update-baseline');
        $showProgress = !$isJson && !(bool) $input->getOption('no-progress');

        if ($showProgress) {
            $output->writeln('');
            $output->writeln('  <fg=cyan>Ejecutando ' . count($checks) . ' checks…</>');
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
            $output->writeln('  <fg=green>✓ Baseline actualizado: ' . $count . ' hallazgos registrados.</>');
            $output->writeln('  <fg=gray>→ ' . Baseline::path($absolute) . '</>');
            return Command::SUCCESS;
        }

        $htmlPath = $input->getOption('html');
        if ($htmlPath) {
            $htmlPath = $this->resolveHtmlPath((string) $htmlPath, $absolute);
            (new HtmlReporter())->render($result, $absolute, $htmlPath);
            $output->writeln('');
            $output->writeln('  <fg=green>✓ Reporte HTML generado:</> ' . $htmlPath);
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
