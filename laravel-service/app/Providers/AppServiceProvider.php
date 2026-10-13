<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\TenantNotFoundException;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        JsonResource::withoutWrapping();
        Model::preventLazyLoading(! $this->app->isProduction());

        // Registered here, not in bootstrap/app.php `then:`, because that callback is skipped when routes are cached.
        // A non-numeric id would otherwise reach the database (500 on PostgreSQL) instead of a clean 404.
        Route::bind('tenant', fn (string $value): Tenant => (ctype_digit($value) ? Tenant::find((int) $value) : null)
            ?? throw new TenantNotFoundException('Tenant not found'));
    }
}
