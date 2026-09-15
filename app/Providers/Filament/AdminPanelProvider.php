<?php

namespace App\Providers\Filament;

use App\Filament\Resources\DisapprovedLoanResource;
use App\Filament\Resources\LoanApplicationResource;
use App\Filament\Widgets\StatsOverviewWidget;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('OIC PMIS v3 Portal')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => view('filament.components.topbar-new-mortgage-action'),
            )
            ->navigationGroups([
                NavigationGroup::make('Membership Management'),
                NavigationGroup::make('Loan Operations'),
                NavigationGroup::make('Document Tracking Pipeline')
                    ->collapsed(),
                NavigationGroup::make('System Administration')
                    ->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('For Notary')
                    ->group('Document Tracking Pipeline')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (): string => LoanApplicationResource::getUrl('index', ['stage' => 'for_notary']))
                    ->badge(fn () => LoanApplicationResource::getEloquentQuery()->whereIn('status', ['For Notary'])->count() ?: null, 'warning')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.loan-applications.index') && in_array(request()->query('stage'), ['for_notary', 'For Notary']))
                    ->sort(1),

                NavigationItem::make('Notarized')
                    ->group('Document Tracking Pipeline')
                    ->icon('heroicon-o-document-check')
                    ->url(fn (): string => LoanApplicationResource::getUrl('index', ['stage' => 'notarized']))
                    ->badge(fn () => LoanApplicationResource::getEloquentQuery()->whereIn('status', ['Notarized'])->count() ?: null, 'info')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.loan-applications.index') && in_array(request()->query('stage'), ['notarized', 'Notarized']))
                    ->sort(2),

                NavigationItem::make('For BIR')
                    ->group('Document Tracking Pipeline')
                    ->icon('heroicon-o-building-office')
                    ->url(fn (): string => LoanApplicationResource::getUrl('index', ['stage' => 'for_bir']))
                    ->badge(fn () => LoanApplicationResource::getEloquentQuery()->whereIn('status', ['For BIR', 'For Bir'])->count() ?: null, 'warning')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.loan-applications.index') && in_array(request()->query('stage'), ['for_bir', 'For BIR', 'For Bir']))
                    ->sort(3),

                NavigationItem::make('BIR Paid')
                    ->group('Document Tracking Pipeline')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (): string => LoanApplicationResource::getUrl('index', ['stage' => 'bir_paid']))
                    ->badge(fn () => LoanApplicationResource::getEloquentQuery()->whereIn('status', ['BIR Paid', 'Bir Paid'])->count() ?: null, 'info')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.loan-applications.index') && in_array(request()->query('stage'), ['bir_paid', 'BIR Paid', 'Bir Paid']))
                    ->sort(4),

                NavigationItem::make('For ROD')
                    ->group('Document Tracking Pipeline')
                    ->icon('heroicon-o-building-library')
                    ->url(fn (): string => LoanApplicationResource::getUrl('index', ['stage' => 'for_rod']))
                    ->badge(fn () => LoanApplicationResource::getEloquentQuery()->whereIn('status', ['For ROD', 'For Rod'])->count() ?: null, 'warning')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.loan-applications.index') && in_array(request()->query('stage'), ['for_rod', 'For ROD', 'For Rod']))
                    ->sort(5),

                NavigationItem::make('ROD Paid')
                    ->group('Document Tracking Pipeline')
                    ->icon('heroicon-o-receipt-percent')
                    ->url(fn (): string => LoanApplicationResource::getUrl('index', ['stage' => 'rod_paid']))
                    ->badge(fn () => LoanApplicationResource::getEloquentQuery()->whereIn('status', ['ROD Paid', 'Rod Paid'])->count() ?: null, 'info')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.loan-applications.index') && in_array(request()->query('stage'), ['rod_paid', 'ROD Paid', 'Rod Paid']))
                    ->sort(6),

                NavigationItem::make('For LTO')
                    ->group('Document Tracking Pipeline')
                    ->icon('heroicon-o-truck')
                    ->url(fn (): string => LoanApplicationResource::getUrl('index', ['stage' => 'for_lto']))
                    ->badge(fn () => LoanApplicationResource::getEloquentQuery()->whereIn('status', ['For LTO', 'For Lto'])->count() ?: null, 'warning')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.loan-applications.index') && in_array(request()->query('stage'), ['for_lto', 'For LTO', 'For Lto']))
                    ->sort(7),

                NavigationItem::make('Encumbered')
                    ->group('Document Tracking Pipeline')
                    ->icon('heroicon-o-check-badge')
                    ->url(fn (): string => LoanApplicationResource::getUrl('index', ['stage' => 'encumbered']))
                    ->badge(fn () => LoanApplicationResource::getEloquentQuery()->whereIn('status', ['Encumbered'])->count() ?: null, 'success')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.loan-applications.index') && in_array(request()->query('stage'), ['encumbered', 'Encumbered']))
                    ->sort(8),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // StatsOverviewWidget is auto-discovered above — do not list again to avoid duplicates
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('System Administration')
                    ->navigationSort(99),
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        MyImages::make()
                            ->directory('images/backgrounds')
                    )
                    ->showAttribution(false),
                \Alsaloul\ImageGallery\ImageGalleryPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
