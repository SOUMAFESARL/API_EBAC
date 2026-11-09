<?php

namespace App\Providers;

use App\Support\Audit\Journal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config()->set('l5-swagger.defaults.generate_always', true);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['created', 'updated', 'deleted'] as $evenement) {
            Event::listen("eloquent.{$evenement}: *", function (string $nom, array $donnees) use ($evenement) {
                if (($donnees[0] ?? null) instanceof Model) {
                    Journal::noter($evenement, $donnees[0]);
                }
            });
        }
    }
}
