<?php

namespace Database\Seeders;

use App\Models\BranchOffice;
use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $butuanBranch = BranchOffice::where('name', 'BUTUAN')->first();
        $cogonBranch = BranchOffice::where('name', 'COGON')->first();
        $valenciaBranch = BranchOffice::where('name', 'VALENCIA')->first();

        $sampleMembers = [
            [
                'member_no' => 'API-2026-00001',
                'first_name' => 'Juan',
                'middle_name' => 'Santos',
                'last_name' => 'Dela Cruz',
                'birth_date' => '1985-06-15',
                'sex' => 'Male',
                'civil_status' => 'Married',
                'email' => 'juan.delacruz@example.com',
                'phone' => '09171234567',
                'address' => 'A.D. Curato St, Butuan City',
                'branch_office_id' => $butuanBranch?->id,
                'is_active' => true,
            ],
            [
                'member_no' => 'API-2026-00002',
                'first_name' => 'Maria',
                'middle_name' => 'Clara',
                'last_name' => 'Rizal',
                'birth_date' => '1990-11-20',
                'sex' => 'Female',
                'civil_status' => 'Single',
                'email' => 'maria.rizal@example.com',
                'phone' => '09189876543',
                'address' => 'Nazareth, Cagayan de Oro City',
                'branch_office_id' => $cogonBranch?->id,
                'is_active' => true,
            ],
            [
                'member_no' => 'API-2026-00003',
                'first_name' => 'Jose',
                'middle_name' => 'Protacio',
                'last_name' => 'Mercado',
                'birth_date' => '1982-03-30',
                'sex' => 'Male',
                'civil_status' => 'Married',
                'email' => 'jose.mercado@example.com',
                'phone' => '09201112233',
                'address' => 'Sayre Hwy, Valencia City, Bukidnon',
                'branch_office_id' => $valenciaBranch?->id,
                'is_active' => true,
            ],
        ];

        foreach ($sampleMembers as $data) {
            Member::updateOrCreate(
                ['member_no' => $data['member_no']],
                $data
            );
        }
    }
}
