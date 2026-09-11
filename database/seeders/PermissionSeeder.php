<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds for Filament Shield role permissions.
     */
    public function run(): void
    {
        $allPermissions = Permission::all();

        // 1. Super Admin - Full Access
        $superAdmin = Role::where('code', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions($allPermissions);
        }

        // Helper function to get permissions by names
        $getPermissions = fn (array $names) => Permission::whereIn('name', $names)->get();

        // 2. Mortgage Document Specialist (Branch Encoder)
        $docSpecialist = Role::where('code', 'mortgage_document_specialist')->first();
        if ($docSpecialist) {
            $docSpecialist->syncPermissions($getPermissions([
                'ViewAny:LoanApplication', 'View:LoanApplication', 'Create:LoanApplication', 'Update:LoanApplication', 'Replicate:LoanApplication',
                'ViewAny:Member', 'View:Member', 'Create:Member', 'Update:Member',
                'ViewAny:BranchOffice', 'View:BranchOffice',
                'ViewAny:RodBranch', 'View:RodBranch',
                'ViewAny:RodStatus', 'View:RodStatus',
                'ViewAny:ApplicationStatus', 'View:ApplicationStatus',
                'View:StatsOverviewWidget',
            ]));
        }

        // 3. Mortgage Assessment Specialist (Head Office Evaluator)
        $assessmentSpecialist = Role::where('code', 'mortgage_assessment_specialist')->first();
        if ($assessmentSpecialist) {
            $assessmentSpecialist->syncPermissions($getPermissions([
                'ViewAny:LoanApplication', 'View:LoanApplication', 'Update:LoanApplication',
                'ViewAny:Member', 'View:Member',
                'ViewAny:BranchOffice', 'View:BranchOffice',
                'ViewAny:RodBranch', 'View:RodBranch',
                'ViewAny:RodStatus', 'View:RodStatus',
                'ViewAny:ApplicationStatus', 'View:ApplicationStatus',
                'View:StatsOverviewWidget',
            ]));
        }

        // 4. Branch Manager & Area Managers
        $readOnlyManagerRoles = [
            'branch_manager',
            'operation_division_manager',
            'chief_executive_officer',
            'area_manager_1',
            'area_manager_2',
            'area_manager_3',
            'audit',
            'appraiser',
            'compliance_specialist',
        ];

        $viewOnlyPermissions = $getPermissions([
            'ViewAny:LoanApplication', 'View:LoanApplication',
            'ViewAny:Member', 'View:Member',
            'ViewAny:BranchOffice', 'View:BranchOffice',
            'ViewAny:RodBranch', 'View:RodBranch',
            'ViewAny:RodStatus', 'View:RodStatus',
            'ViewAny:ApplicationStatus', 'View:ApplicationStatus',
            'View:StatsOverviewWidget',
        ]);

        foreach ($readOnlyManagerRoles as $roleCode) {
            $role = Role::where('code', $roleCode)->first();
            if ($role) {
                $role->syncPermissions($viewOnlyPermissions);
            }
        }
    }
}
