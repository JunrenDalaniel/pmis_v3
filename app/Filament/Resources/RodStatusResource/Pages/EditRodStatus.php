<?php

namespace App\Filament\Resources\RodStatusResource\Pages;

use App\Filament\Resources\RodStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRodStatus extends EditRecord
{
    protected static string $resource = RodStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
