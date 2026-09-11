<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        if ($user) {
            $allowed = $user->getAllowedBranchIds();
            if (is_array($allowed) && !empty($allowed)) {
                if (empty($data['branch_office_id']) || !in_array((int) $data['branch_office_id'], $allowed)) {
                    $data['branch_office_id'] = $this->record->branch_office_id ?? $allowed[0];
                }
            }
        }

        return $data;
    }
}
