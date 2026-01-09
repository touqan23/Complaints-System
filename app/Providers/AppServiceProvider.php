<?php

namespace App\Providers;

use App\Repositories\Cached\CachedComplaintRepository;
use App\Repositories\Cached\CachedEmployeeRepository;
use App\Repositories\Cached\CachedUserRepository;
use App\Repositories\Eloquent\ComplaintRepository;
use App\Repositories\Eloquent\EmployeeRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmployeeRepositoryInterface::class, function ($app) {
            return new CachedEmployeeRepository(
                new EmployeeRepository(
                    $app->make(\App\Models\Employee::class)
                )
            );
        });

        $this->app->bind(ComplaintRepositoryInterface::class, function ($app) {
            return new CachedComplaintRepository(
                new ComplaintRepository(
                    $app->make(\App\Models\Complaint::class)
                )
            );
        });

        $this->app->bind(UserRepositoryInterface::class, function ($app) {
            return new CachedUserRepository(
                new UserRepository(
                    $app->make(\App\Models\User::class)
                )
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }


}
