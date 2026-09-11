<?php

namespace Database\Seeders;

use App\Models\LoanApplication;
use App\Models\Member;
use App\Models\RodBranch;
use App\Models\RodStatus;
use Illuminate\Database\Seeder;

class LoanApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $member1 = Member::first();
        $member2 = Member::skip(1)->first();
        $member3 = Member::skip(2)->first();
        $entryStatus = RodStatus::where('step_number', 1)->first();
        $butuanRod = RodBranch::where('name', 'Butuan City')->first();

        if ($member1) {
            $loan1 = LoanApplication::updateOrCreate(
                ['application_no' => 'API-LA-2026-00001'],
                [
                    'member_id' => $member1->id,
                    'branch_office_id' => $member1->branch_office_id,
                    'requested_amount' => 750000.00,
                    'collateral_type' => 'real_estate_mortgage',
                    'status' => 'Initial Assessment',
                    'current_rod_status_id' => $entryStatus?->id,
                    'current_rod_branch_id' => $butuanRod?->id,
                    'notes' => 'Real Estate Mortgage Loan Application for Commercial Property.',
                ]
            );

            $loan1->realEstateCollaterals()->updateOrCreate(
                ['title_number' => 'TCT-987654'],
                [
                    'title_details' => 'TCT (Transfer Certificate of Title)',
                    'title_owner_name' => $member1->full_name,
                    'tax_dec_land' => 'TDL-2026-999',
                    'land_owner_name' => $member1->full_name,
                    'pin_land' => '162-01-0001-015-04',
                    'tax_dec_bldg' => 'TDB-2026-888',
                    'bldg_owner_name' => $member1->full_name,
                    'pin_bldg' => '162-01-0001-015-05',
                    'rem_status' => 'Clean Title',
                ]
            );

            LoanApplication::updateOrCreate(
                ['application_no' => 'API-LA-2026-00003'],
                [
                    'member_id' => $member1->id,
                    'branch_office_id' => $member1->branch_office_id,
                    'requested_amount' => 1200000.00,
                    'collateral_type' => 'real_estate_mortgage',
                    'status' => 'For BIR',
                    'current_rod_status_id' => $entryStatus?->id,
                    'current_rod_branch_id' => $butuanRod?->id,
                    'notes' => 'Application undergoing BIR tax clearance and documentary stamp process.',
                ]
            );
        }

        if ($member2) {
            $loan2 = LoanApplication::updateOrCreate(
                ['application_no' => 'API-LA-2026-00002'],
                [
                    'member_id' => $member2->id,
                    'branch_office_id' => $member2->branch_office_id,
                    'requested_amount' => 450000.00,
                    'collateral_type' => 'chattel_mortgage',
                    'status' => 'For Notary',
                    'current_rod_status_id' => $entryStatus?->id,
                    'notes' => 'Chattel Mortgage Loan Application for Utility Vehicle pending notarization.',
                ]
            );

            $loan2->chattelCollaterals()->updateOrCreate(
                ['plate_no' => 'ABC-1234'],
                [
                    'owner_name' => $member2->full_name,
                    'mv_file_no' => 'MV-88371920',
                    'engine_no' => 'ENG-4D56-9921',
                    'chassis_no' => 'CHS-KBD41-8821',
                    'year_model' => '2023',
                    'vehicle_description' => 'Toyota Hilux 2.4L 4x2 MT White',
                    'registration_renewal_date' => now()->addMonths(6)->toDateString(),
                    'encumbered_to' => 'Clean',
                    'cm_status' => 'Active',
                ]
            );
        }

        if ($member3) {
            LoanApplication::updateOrCreate(
                ['application_no' => 'API-LA-2026-00004'],
                [
                    'member_id' => $member3->id,
                    'branch_office_id' => $member3->branch_office_id,
                    'requested_amount' => 950000.00,
                    'collateral_type' => 'real_estate_mortgage',
                    'status' => 'For ROD',
                    'current_rod_status_id' => $entryStatus?->id,
                    'current_rod_branch_id' => $butuanRod?->id,
                    'notes' => 'Mortgage documents submitted for Registry of Deeds entry annotation.',
                ]
            );
        }
    }
}
