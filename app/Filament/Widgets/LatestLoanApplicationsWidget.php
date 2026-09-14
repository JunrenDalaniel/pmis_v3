<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LoanApplicationResource;
use App\Models\ApplicationStatus;
use App\Models\LoanApplication;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestLoanApplicationsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $heading = 'Recent Loan Applications & Document Aging';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();

        $query = LoanApplication::query()
            ->with(['member', 'branchOffice'])
            ->latest('id');

        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('application_no')
                    ->label('Application ID')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('borrower_name')
                    ->label('Borrower Name')
                    ->state(function (LoanApplication $record): string {
                        if (! $record->member) {
                            return 'N/A';
                        }
                        return "{$record->member->last_name}, {$record->member->first_name}";
                    })
                    ->description(fn (LoanApplication $record): string => $record->branchOffice?->name ?? 'No Branch'),

                Tables\Columns\TextColumn::make('requested_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => '₱' . number_format((float) $state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Approval Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'Approved'       => 'success',
                        'Branch Updated' => 'warning',
                        'Disapproved'    => 'danger',
                        default          => 'warning',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Current Stage')
                    ->badge()
                    ->color(function ($state, LoanApplication $record): string {
                        if (in_array($record->sub_status, ['internal_deficiency', 'external_deficiency'])) {
                            return 'danger';
                        }
                        $dbStatus = ApplicationStatus::where('name', $state)->first();
                        return $dbStatus?->color ?? 'info';
                    }),

                Tables\Columns\TextColumn::make('days_in_stage')
                    ->label('Aging / SLA')
                    ->state(function (LoanApplication $record): string {
                        $ref = $record->status_changed_at
                            ? Carbon::parse($record->status_changed_at)
                            : ($record->created_at ? Carbon::parse($record->created_at) : now());
                        $days = (int) $ref->diffInDays(now());
                        return $days === 0 ? 'Today' : "{$days}d in stage";
                    })
                    ->badge()
                    ->color(function (LoanApplication $record): string {
                        if (in_array($record->sub_status, ['internal_deficiency', 'external_deficiency'])) {
                            return 'danger';
                        }
                        $ref = $record->status_changed_at
                            ? Carbon::parse($record->status_changed_at)
                            : ($record->created_at ? Carbon::parse($record->created_at) : now());
                        $days = (int) $ref->diffInDays(now());
                        if ($days >= 11) return 'danger';
                        if ($days >= 6)  return 'warning';
                        return 'success';
                    }),
            ])
            ->actions([
                Action::make('open')
                    ->label('View')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(function (LoanApplication $record): string {
                        if ($record->approval_status === 'Pending Approval') {
                            return \App\Filament\Resources\ForApprovalResource::getUrl('edit', ['record' => $record]);
                        }
                        if ($record->approval_status === 'Disapproved') {
                            return \App\Filament\Resources\DisapprovedLoanResource::getUrl('edit', ['record' => $record]);
                        }
                        return LoanApplicationResource::getUrl('edit', ['record' => $record]);
                    }),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}