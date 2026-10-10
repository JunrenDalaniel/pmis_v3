<?php

namespace Database\Seeders;

use App\Models\BranchOffice;
use Illuminate\Database\Seeder;

class BranchOfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'BUTUAN',
                'area' => 'Area 3',
                'address' => 'A.D. Curato St, Butuan City, 8600 Agusan Del Norte',
            ],
            [
                'name' => 'BALINGASAG',
                'area' => 'Area 3',
                'address' => 'Madroño Bldg., Brgy. 4 National Highway, Balingasag, Misamis Oriental',
            ],
            [
                'name' => 'AGORA',
                'area' => 'Area 3',
                'address' => 'Northern Mindanao, Macabalan, Cagayan de Oro City',
            ],
            [
                'name' => 'AGLAYAN',
                'area' => 'Area 2',
                'address' => 'Sayre Hwy, Malaybalay, Bukidnon',
            ],
            [
                'name' => 'BULUA',
                'area' => 'Area 1',
                'address' => 'Door #3, LSA Bldg., Zone 2 Upper Bulua, Cagayan de Oro City, Misamis Oriental',
            ],
            [
                'name' => 'BAUNGON',
                'area' => 'Area 1',
                'address' => 'Imbatog, Baungon, Bukidnon',
            ],
            [
                'name' => 'COGON',
                'area' => 'Area 1',
                'address' => 'OIC Bldg., 8th - 22nd Street, Nazareth, Cagayan de Oro City',
            ],
            [
                'name' => 'DON CARLOS',
                'area' => 'Area 2',
                'address' => 'Sayre Hwy, Don Carlos, Bukidnon',
            ],
            [
                'name' => 'EL SALVADOR',
                'area' => 'Area 1',
                'address' => 'El Salvador City, Cagayan de Oro City, Misamis Oriental',
            ],
            [
                'name' => 'GINGOOG',
                'area' => 'Area 3',
                'address' => 'Dona Graciana Street, Gingoog City',
            ],
            [
                'name' => 'MARAMAG',
                'area' => 'Area 2',
                'address' => 'Telmo Building, Purok 3 Bukidnon National School of Home Industries Rd, Maramag',
            ],
            [
                'name' => 'MANOLO',
                'area' => 'Area 2',
                'address' => 'National Highway, Poblacion, Manolo Fortich, 8703 Bukidnon',
            ],
            [
                'name' => 'PUERTO',
                'area' => 'Area 3',
                'address' => 'OIC Bldg., Tin - Ao, Agusan, National Highway, Cagayan de Oro City, Misamis Oriental',
            ],
            [
                'name' => 'TAGBILARAN',
                'area' => 'Area 3',
                'address' => 'Save & Earn, 13 LDG J.S. Torralba St, Tagbilaran City, 6300 Bohol',
            ],
            [
                'name' => 'TALAKAG',
                'area' => 'Area 1',
                'address' => 'OIC Building, Talakag, Bukidnon',
            ],
            [
                'name' => 'TUBIGON',
                'area' => 'Area 3',
                'address' => 'Tubigon Bohol',
            ],
            [
                'name' => 'UBAY',
                'area' => 'Area 3',
                'address' => 'Tan Nene Street, Poblacion, Ubay Bohol',
            ],
            [
                'name' => 'VALENCIA',
                'area' => 'Area 2',
                'address' => '13 Sayre Hwy, Valencia City, Bukidnon',
            ],
            [
                'name' => 'YACAPIN',
                'area' => 'Area 1',
                'address' => 'OIC Building, Tiano Yacapin Street, Cagayan de Oro, 9000 Misamis Oriental',
            ],
            [
                'name' => 'CARMEN',
                'area' => 'Area 1',
                'address' => 'Ipil street carmen, CDOC',
            ],
            [
                'name' => 'TORIL',
                'area' => 'Area 2',
                'address' => 'Toril, Davao City',
            ],
            [
                'name' => 'ILUSTRE',
                'area' => 'Area 2',
                'address' => 'ILUSTRE DAVAO CITY',
            ],
            [
                'name' => 'BAYUGAN',
                'area' => 'Area 3',
                'address' => 'PUROK 22, POBLACION BAYUGAN CITY',
            ],
            [
                'name' => 'ILIGAN',
                'area' => 'Area 1',
                'address' => "GROUND FLOOR, MICHAELLA'S PLAZA, NATIONAL HIGHWAY, TIBANGA, ILIGAN CITY",
            ],
        ];

        foreach ($branches as $index => $branch) {
            $branchId = 'BR-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            BranchOffice::updateOrCreate(
                ['name' => $branch['name']],
                [
                    'branch_id' => $branchId,
                    'area' => $branch['area'],
                    'address' => $branch['address'],
                    'is_active' => true,
                ]
            );
        }
    }
}
