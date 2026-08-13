<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Without this a single resource is wrapped in "data" while the same
        // resource nested in a response is not, so the client would have to
        // handle two shapes. Envelope keys are named explicitly instead.
        // Paginated collections keep their own data/meta/links structure.
        JsonResource::withoutWrapping();
    }
}
