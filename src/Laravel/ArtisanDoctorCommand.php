<?php

declare(strict_types=1);

namespace LaravelDoctor\Laravel;

use Illuminate\Console\Command;
use LaravelDoctor\Baseline;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Config;
use LaravelDoctor\Console\ConsoleReporter;
use LaravelDoctor\Console\HtmlReporter;
use LaravelDoctor\Console\JsonReporter;
use LaravelDoctor\Doctor;

final class ArtisanDoctorCommand extends Command
{
    protected $signature = 'doctor
        {path? : Ruta a la raíz del proyecto Laravel (por defecto base_path()).}
        {--json : Emite JSON en lugar de salida legible.}
        {--min-score=0 : Falla cuando la puntuación es menor a este umbral.}
        {--category= : Ejecuta solo los checks de una categoría.}
        {--baseline : Usa .laravel-doctor.baseline.json — solo cuentan los hallazgos nuevos.}
        {--update-baseline : Guarda los hallazgos actuales como nuevo baseline y sale.}
        {--html= : Genera un reporte HTML interactivo en la ruta indicada.}';

    protected $description = 'Diagnostica este proyecto Laravel (seguridad, rendimiento, arquitectura, calidad).';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path();
        $absolute = realpath($path) ?: $path;

        $context = new CheckContext($absolute);
        $config = Config::loadFromProject($absolute);

        $checks = Doctor::defaultChecks();
        $category = $this->option('category');
        if ($category) {
            $checks = array_values(array_filter($checks, fn ($c) => $c->category() === $category));
            if (empty($checks)) {
                $this->error('Categoría desconocida: ' . $category);
                return self::FAILURE;
            }
        }

        $isJson = (bool) $this->option('json');
        $useBaseline = (bool) $this->option('baseline');
        $updateBaseline = (bool) $this->option('update-baseline');

        if (!$isJson) {
            $this->getOutput()->writeln('');
            $this->getOutput()->writeln('  <fg=cyan>Ejecutando ' . count($checks) . ' checks…</>');
        }

        $doctor = new Doctor($checks);
        $baseline = ($useBaseline && !$updateBaseline) ? Baseline::load($absolute) : null;

        $result = $doctor->diagnose(
            $context,
            $isJson ? null : function ($check) {
                $this->getOutput()->writeln('  <fg=gray>· ' . $check->id() . '</>');
            },
            $config,
            $baseline,
        );

        if ($updateBaseline) {
            $allResult = $doctor->diagnose($context, null, $config, null);
            $count = Baseline::write($absolute, $allResult['findings']);
            $this->getOutput()->writeln('');
            $this->getOutput()->writeln('  <fg=green>✓ Baseline actualizado: ' . $count . ' hallazgos registrados.</>');
            $this->getOutput()->writeln('  <fg=gray>→ ' . Baseline::path($absolute) . '</>');
            return self::SUCCESS;
        }

        $htmlPath = $this->option('html');
        if ($htmlPath) {
            $resolved = preg_match('#^[a-zA-Z]:[\\\\/]#', $htmlPath) || str_starts_with($htmlPath, '/')
                ? $htmlPath
                : rtrim($absolute, '/\\') . DIRECTORY_SEPARATOR . $htmlPath;
            (new HtmlReporter())->render($result, $absolute, $resolved);
            $this->getOutput()->writeln('');
            $this->getOutput()->writeln('  <fg=green>✓ Reporte HTML generado:</> ' . $resolved);
        }

        if ($isJson) {
            (new JsonReporter($this->getOutput()))->render($result, $absolute);
        } else {
            (new ConsoleReporter($this->getOutput()))->render($result, $absolute);
        }

        $minScore = (int) $this->option('min-score');
        return $result['score'] < $minScore ? self::FAILURE : self::SUCCESS;
    }
}
