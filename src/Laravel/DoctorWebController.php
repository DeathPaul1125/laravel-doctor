<?php

declare(strict_types=1);

namespace LaravelDoctor\Laravel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use LaravelDoctor\Baseline;
use LaravelDoctor\CheckContext;
use LaravelDoctor\Config;
use LaravelDoctor\Console\HtmlReporter;
use LaravelDoctor\Doctor;

/**
 * Web endpoints for the live doctor dashboard. Mounted only in non-production environments
 * by DoctorServiceProvider.
 */
final class DoctorWebController
{
    public function html(): Response
    {
        $result = $this->diagnose();
        $html = (new HtmlReporter())->buildHtml($result, base_path());
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function json(): JsonResponse
    {
        $result = $this->diagnose();
        return new JsonResponse([
            'project' => base_path(),
            'score' => $result['score'],
            'grade' => $result['grade'],
            'totals' => [
                'findings' => count($result['findings']),
                'byCategory' => $result['byCategory'],
                'baselineKnown' => $result['baselineCount'] ?? 0,
            ],
            'findings' => array_map(fn ($f) => $f->toArray(), $result['findings']),
        ]);
    }

    private function diagnose(): array
    {
        $root = base_path();
        $context = new CheckContext($root);
        $config = Config::loadFromProject($root);
        $baseline = file_exists(Baseline::path($root)) ? Baseline::load($root) : null;
        return (new Doctor())->diagnose($context, null, $config, $baseline);
    }
}
