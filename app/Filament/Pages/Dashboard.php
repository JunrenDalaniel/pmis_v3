<?php

namespace App\Filament\Pages;

use App\Models\BranchOffice;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Dashboard';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'sm' => 2,
                'md' => 3,
                'xl' => 5,
            ])
            ->components([
                Select::make('date_preset')
                    ->label('Period Preset')
                    ->options([
                        'all'          => '🌐 All Record',
                        'this_month'   => '🗓️ This Month',
                        'last_month'   => '⏮️ Last Month',
                        'midyear'      => '🌓 Midyear',
                        'this_year'    => '📈 This Year',
                        'last_year'    => '📜 Last Year',
                        'custom'       => '⚙️ Custom Date Range',
                    ])
                    ->default('all')
                    ->live(),

                DatePicker::make('startDate')
                    ->label('Start Date')
                    ->placeholder('From date')
                    ->maxDate(now())
                    ->visible(fn ($get) => $get('date_preset') === 'custom'),

                DatePicker::make('endDate')
                    ->label('End Date')
                    ->placeholder('To date')
                    ->visible(fn ($get) => $get('date_preset') === 'custom'),

                Select::make('branch_office_id')
                    ->label('Branch Office')
                    ->placeholder('All Branches')
                    ->options(function () {
                        $user = auth()->user();
                        $allowed = $user?->getAllowedBranchIds();
                        $q = BranchOffice::query()->where('is_active', true)->orderBy('name');
                        if (is_array($allowed)) {
                            $q->whereIn('id', $allowed);
                        }
                        return $q->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload(),

                Select::make('collateral_type')
                    ->label('Collateral Type')
                    ->placeholder('All Collateral Types')
                    ->options([
                        'real_estate_mortgage' => 'Real Estate Mortgage (REM)',
                        'chattel_mortgage'     => 'Chattel Mortgage (CM)',
                    ]),
            ]);
    }
}