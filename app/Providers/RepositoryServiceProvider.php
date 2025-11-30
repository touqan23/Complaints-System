<?php

namespace App\Providers;

use App\Repositories\Eloquent\ComplaintRepository;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(
            \App\Repositories\Interfaces\UserRepositoryInterface::class,
            \App\Repositories\Eloquent\UserRepository::class
        );

        $this->app->bind(
            \App\Repositories\Interfaces\CitizenRepositoryInterface::class,
            \App\Repositories\Eloquent\CitizenRepository::class
        );

        $this->app->bind(
            \App\Repositories\Interfaces\EmployeeRepositoryInterface::class,
            \App\Repositories\Eloquent\EmployeeRepository::class
        );

        $this->app->bind(
            ComplaintRepositoryInterface::class,
            ComplaintRepository::class
        );
    }

    public function boot() {}
}


