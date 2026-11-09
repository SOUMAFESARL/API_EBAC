<?php

use App\Http\Middleware\AutoriserRoles;
use App\Http\Middleware\InterdireRoles;
use App\Http\Middleware\JournaliserAction;
use App\Http\Middleware\VerifierAdministrateur;
use App\Http\Middleware\VerifierCompteActif;
use App\Http\Middleware\VerifierPermission;
use App\Support\Audit\Journal;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(null);
        $middleware->api(append: [JournaliserAction::class]);

        $middleware->alias([
            'administrateur' => VerifierAdministrateur::class,
            'roles.autorises' => AutoriserRoles::class,
            'compte.actif' => VerifierCompteActif::class,
            'permission' => VerifierPermission::class,
            'roles.interdits' => InterdireRoles::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->report(function (Throwable $exception) {
            if (! app()->bound('request') || ! request()->is('api/*')) {
                return;
            }

            Journal::consigner('incidents', 'Erreur serveur — '.class_basename($exception), 'critique',
                ['type' => 'Requête', 'libelle' => request()->method().' /'.request()->path()],
                apres: ['Message' => Str::limit($exception->getMessage(), 250)]);
        });
    })->create();
