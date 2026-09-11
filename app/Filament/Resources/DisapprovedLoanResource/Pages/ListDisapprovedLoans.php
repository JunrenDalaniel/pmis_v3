<?php

namespace App\Filament\Resources\DisapprovedLoanResource\Pages;

use App\Filament\Resources\DisapprovedLoanResource;
use Filament\Resources\Pages\ListRecords;

class ListDisapprovedLoans extends ListRecords
{
    protected static string $resource = DisapprovedLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

