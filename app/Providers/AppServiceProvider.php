<?php

namespace App\Providers;

use App\Models\LoanApplication;
use App\Observers\LoanApplicationObserver;
use Filament\Forms\Components\DatePicker;
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
        LoanApplication::observe(LoanApplicationObserver::class);

        DatePicker::configureUsing(function (DatePicker $datePicker): void {
            $datePicker->native(false);
        });
    }
}
