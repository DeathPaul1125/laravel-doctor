<?php

declare(strict_types=1);

namespace LaravelDoctor\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DoctorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ArtisanDoctorCommand::class,
            ]);
        }

        // Mount the web dashboard only outside production to avoid exposing internals.
        if ($this->app->environment(['local', 'development', 'testing', 'staging'])) {
            Route::middleware('web')
                ->prefix('doctor')
                ->group(function () {
                    Route::get('/', [DoctorWebController::class, 'html'])->name('doctor.html');
                    Route::get('/json', [DoctorWebController::class, 'json'])->name('doctor.json');
                });
        }
    }
}
