<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Enums\RoleEnum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Admin gate - used in routes
        Gate::define('admin', function ($user) {
            return $user->role === RoleEnum::ADMINISTRATOR;
        });

        // Supervisor gate
        Gate::define('supervisor', function ($user) {
            return in_array($user->role, [RoleEnum::SUPERVISOR, RoleEnum::ADMINISTRATOR]);
        });

        // Management gate
        Gate::define('management', function ($user) {
            return in_array($user->role, [RoleEnum::MANAJEMEN, RoleEnum::ADMINISTRATOR]);
        });

        // Dashboard access gate
        Gate::define('dashboard', function ($user) {
            return in_array($user->role, [
                RoleEnum::SUPERVISOR,
                RoleEnum::ADMINISTRATOR,
                RoleEnum::MANAJEMEN,
                RoleEnum::KEPALA_RUANGAN,
            ]);
        });
    }
}
