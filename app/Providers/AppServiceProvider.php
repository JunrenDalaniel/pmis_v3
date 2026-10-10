<?php

namespace App\Providers;

use App\Listeners\AuthenticationLogSubscriber;
use App\Models\AccountLog;
use App\Models\LoanApplication;
use App\Observers\ActivityObserver;
use App\Observers\LoanApplicationObserver;
use App\Policies\AccountLogPolicy;
use App\Policies\ActivityPolicy;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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
        Gate::policy(AccountLog::class, AccountLogPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);

        LoanApplication::observe(LoanApplicationObserver::class);
        Activity::observe(ActivityObserver::class);

        // Register authentication event subscriber for login/logout/failed tracking
        Event::subscribe(AuthenticationLogSubscriber::class);

        DatePicker::configureUsing(function (DatePicker $datePicker): void {
            $datePicker->native(false);
        });
    }
}
