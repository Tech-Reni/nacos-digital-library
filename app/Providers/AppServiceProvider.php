<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $production = $this->app->isProduction();

        // Outside production, fail loudly on N+1 queries (lazy loading),
        // silently discarded attributes and typos in attribute names.
        Model::shouldBeStrict(! $production);

        // Never allow migrate:fresh, db:wipe and friends against production.
        DB::prohibitDestructiveCommands($production);

        if ($production) {
            URL::forceHttps();
        }

        Password::defaults(function () use ($production) {
            $rule = Password::min(8)->letters()->mixedCase()->numbers()->symbols();

            return $production ? $rule->uncompromised() : $rule;
        });
    }
}
