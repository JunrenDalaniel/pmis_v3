<?php

namespace App\Filament\Resources\LoanApplicationResource\Pages;

use App\Filament\Resources\LoanApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLoanApplications extends ListRecords
{
    protected static string $resource = LoanApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Application'),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery()
            ->with(['member', 'branchOffice', 'chattelCollaterals', 'realEstateCollaterals']);

        $stage = request()->query('stage');

        if (filled($stage)) {
            $stageMap = [
                'initial_assessment' => ['Initial Assessment'],
                'for_notary'         => ['For Notary'],
                'notarized'          => ['Notarized'],
                'for_bir'            => ['For BIR', 'For Bir'],
                'bir_paid'           => ['BIR Paid', 'Bir Paid'],
                'for_rod'            => ['For ROD', 'For Rod'],
                'rod_paid'           => ['ROD Paid', 'Rod Paid'],
                'for_ppsr'           => ['For PPSR', 'For Ppsr'],
                'ppsr_paid'          => ['PPSR Paid', 'Ppsr Paid'],
                'for_lto'                    => ['For LTO', 'For Lto'],
                'encumbered'                 => ['Encumbered'],
                'fully_paid'                 => ['Fully Paid'],
                'released_self_cancellation' => ['Released For Self-Cancellation', 'Released for Self-Cancellation'],
                'for_cancellation'           => ['For Cancellation'],
                'mortgage_released'          => ['Mortgage Released'],
            ];

            // Sanitize: only allow known stage keys to prevent unexpected query values
            if (!array_key_exists(strtolower($stage), $stageMap)) {
                return $query; // unknown stage — return unfiltered
            }

            $statuses = $stageMap[strtolower($stage)];
            $query->whereIn('status', $statuses);
        }

        return $query;
    }
}
