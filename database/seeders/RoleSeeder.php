<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'guard_name' => 'web',
                'code' => 'super_admin',
                'scope' => 'head_office',
                'description' => 'Super Administrator with full system control and permission management.',
            ],
            [
                'name' => 'Mortgage Document Specialist',
                'guard_name' => 'web',
                'code' => 'mortgage_document_specialist',
                'scope' => 'branch',
                'description' => 'Create/encode loan entries; update branch-side information; upload documents; add comments; respond to deficiencies; upload corrected/additional documents; monitor branch applications.',
            ],
            [
                'name' => 'Branch Manager',
                'guard_name' => 'web',
                'code' => 'branch_manager',
                'scope' => 'branch',
                'description' => 'View records of the branch; monitor collateralized loans; follow up with the Mortgage Document Specialist regarding member loan status/action; no assessment function.',
            ],
            [
                'name' => 'Mortgage Assessment Specialist',
                'guard_name' => 'web',
                'code' => 'mortgage_assessment_specialist',
                'scope' => 'head_office',
                'description' => 'Evaluate applications; view branch-uploaded documents; verify completeness/correctness; record notes and discrepancies/deficiencies; review corrections; update assessment and processing status.',
            ],
            [
                'name' => 'Operation Division Manager (ODM)',
                'guard_name' => 'web',
                'code' => 'operation_division_manager',
                'scope' => 'head_office',
                'description' => 'View all collateralized loan records, collateral information, authorized documents, dashboards and reports; download authorized documents.',
            ],
            [
                'name' => 'Chief Executive Officer (CEO)',
                'guard_name' => 'web',
                'code' => 'chief_executive_officer',
                'scope' => 'head_office',
                'description' => 'View all collateralized loan records, collateral information, authorized documents, dashboards and reports; download authorized documents.',
            ],
            [
                'name' => 'Area Manager 1 (AM1)',
                'guard_name' => 'web',
                'code' => 'area_manager_1',
                'scope' => 'head_office',
                'description' => 'View branches assigned to Area 1; monitor loan status and follow up with branch management for member-loan compliance.',
            ],
            [
                'name' => 'Area Manager 2 (AM2)',
                'guard_name' => 'web',
                'code' => 'area_manager_2',
                'scope' => 'head_office',
                'description' => 'View branches assigned to Area 2; monitor loan status and follow up with branch management for member-loan compliance.',
            ],
            [
                'name' => 'Area Manager 3 (AM3)',
                'guard_name' => 'web',
                'code' => 'area_manager_3',
                'scope' => 'head_office',
                'description' => 'View branches assigned to Area 3; monitor loan status and follow up with branch management for member-loan compliance.',
            ],
            [
                'name' => 'Audit',
                'guard_name' => 'web',
                'code' => 'audit',
                'scope' => 'head_office',
                'description' => 'View authorized collateralized loan records, documents, workflow history, reports and audit information; download authorized documents.',
            ],
            [
                'name' => 'Appraiser',
                'guard_name' => 'web',
                'code' => 'appraiser',
                'scope' => 'head_office',
                'description' => 'View authorized loan/collateral information and download documents needed for appraisal.',
            ],
            [
                'name' => 'Compliance Specialist',
                'guard_name' => 'web',
                'code' => 'compliance_specialist',
                'scope' => 'head_office',
                'description' => 'View authorized loan records, documents, deficiencies and compliance information; download authorized documents.',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['code' => $roleData['code']],
                $roleData
            );
        }
    }
}
