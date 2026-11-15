<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Catch N+1 problems while developing.
        Model::preventLazyLoading(! $this->app->isProduction());

        // General permissions (Gates). Record level rules live in app/Policies.
        Gate::define('view_department_reports', fn (User $u) => $u->hasRole(Role::DepartmentHead));

        Gate::define('manage_equipment', fn (User $u) => $u->hasRole(Role::LabManager, Role::DepartmentHead));

        Gate::define('reserve_equipment', fn (User $u) => $u->hasRole(Role::Student, Role::Faculty));
    }
}
