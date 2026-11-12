<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\OfferedService;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\TariffParam;
use App\Policies\CatalogPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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
        foreach ([Category::class, Project::class, OfferedService::class, TariffParam::class] as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }
        Gate::policy(ServiceRequest::class, ServiceRequestPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Paginator::useBootstrapFive();
    }
}
