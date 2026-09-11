<?php

namespace Database\Seeders;

use App\Models\RodStatus;
use Illuminate\Database\Seeder;

class RodStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'step_number' => 1,
                'name' => 'Entry',
                'description' => 'Initial entry of documents into the Registry of Deeds process.',
            ],
            [
                'step_number' => 2,
                'name' => 'Payment',
                'description' => 'Payment of required registration fees and dues.',
            ],
            [
                'step_number' => 3,
                'name' => 'Encoding',
                'description' => 'Encoding of transaction details into the official system.',
            ],
            [
                'step_number' => 4,
                'name' => 'Examination',
                'description' => 'Legal and administrative examination of submitted documents.',
            ],
            [
                'step_number' => 5,
                'name' => 'Approval',
                'description' => 'Formal approval by the Registrar of Deeds or authorized officer.',
            ],
            [
                'step_number' => 6,
                'name' => 'Scan/Uploading',
                'description' => 'Digital scanning and uploading of approved land titles and documents.',
            ],
            [
                'step_number' => 7,
                'name' => 'Printing',
                'description' => 'Printing of official certificates of title / annotated documents.',
            ],
            [
                'step_number' => 8,
                'name' => 'Releasing',
                'description' => 'Document prepared for release to owner or authorized representative.',
            ],
            [
                'step_number' => 9,
                'name' => 'Closed',
                'description' => 'Process completed and transaction officially closed.',
            ],
        ];

        foreach ($statuses as $status) {
            RodStatus::updateOrCreate(
                ['step_number' => $status['step_number']],
                [
                    'name' => $status['name'],
                    'description' => $status['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
