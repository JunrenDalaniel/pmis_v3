<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user) {
            $allowed = $user->getAllowedBranchIds();
            // If user has restricted branch access (e.g. branch account)
            if (is_array($allowed) && !empty($allowed)) {
                if (empty($data['branch_office_id']) || !in_array((int) $data['branch_office_id'], $allowed)) {
                    $data['branch_office_id'] = $allowed[0];
                }
            }
        }

        $year = date('Y');
        $nextId = (\App\Models\Member::max('id') ?? 0) + 1;
        $memberNo = $data['member_no'] ?? ('API-' . $year . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));
        while (\App\Models\Member::where('member_no', $memberNo)->exists()) {
            $nextId++;
            $memberNo = 'API-' . $year . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
        }
        $data['member_no'] = $memberNo;

        return $data;
    }
}
