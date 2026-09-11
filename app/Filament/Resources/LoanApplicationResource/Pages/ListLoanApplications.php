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
            Actions\CreateAction::make(),
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
                'for_lto'            => ['For LTO', 'For Lto'],
                'encumbered'         => ['Encumbered'],
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
