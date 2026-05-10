<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        // Customers
        $customers = [
            [
                'name' => 'Addis Ababa University Press',
                'phone' => '+251911234567',
                'email' => 'press@aau.edu.et',
                'address' => 'Sidist Kilo, Addis Ababa',
                'tin_number' => 'TIN-AAU-001',
                'is_customer' => true,
                'is_supplier' => false,
            ],
            [
                'name' => 'Ethiopian Airlines',
                'phone' => '+251116179900',
                'email' => 'procurement@ethiopianairlines.com',
                'address' => 'Bole International Airport, Addis Ababa',
                'tin_number' => 'TIN-ETH-002',
                'is_customer' => true,
                'is_supplier' => false,
            ],
            [
                'name' => 'Commercial Bank of Ethiopia',
                'phone' => '+251115514000',
                'email' => 'info@combanketh.et',
                'address' => 'Gambia Street, Addis Ababa',
                'tin_number' => 'TIN-CBE-003',
                'is_customer' => true,
                'is_supplier' => false,
            ],
            [
                'name' => 'Dashen Bank',
                'phone' => '+251116183000',
                'email' => 'info@dashenbank.com',
                'address' => 'Bole Road, Addis Ababa',
                'tin_number' => 'TIN-DSH-004',
                'is_customer' => true,
                'is_supplier' => false,
            ],
            [
                'name' => 'Ethio Telecom',
                'phone' => '+251115500000',
                'email' => 'corporate@ethiotelecom.et',
                'address' => 'Churchill Avenue, Addis Ababa',
                'tin_number' => 'TIN-ETC-005',
                'is_customer' => true,
                'is_supplier' => false,
            ],
            [
                'name' => 'Ministry of Education',
                'phone' => '+251115530333',
                'email' => 'info@moe.gov.et',
                'address' => 'Arat Kilo, Addis Ababa',
                'tin_number' => 'TIN-MOE-006',
                'is_customer' => true,
                'is_supplier' => false,
            ],
            [
                'name' => 'Awash Bank',
                'phone' => '+251116614482',
                'email' => 'info@awashbank.com',
                'address' => 'Ras Desta Damtew Street, Addis Ababa',
                'tin_number' => 'TIN-AWB-007',
                'is_customer' => true,
                'is_supplier' => false,
            ],
            [
                'name' => 'Abyssinia Bank',
                'phone' => '+251116622000',
                'email' => 'info@bankofabyssinia.com',
                'address' => 'Gambia Street, Addis Ababa',
                'tin_number' => 'TIN-BOA-008',
                'is_customer' => true,
                'is_supplier' => false,
            ],
        ];

        // Suppliers
        $suppliers = [
            [
                'name' => 'Mega Paper Trading',
                'phone' => '+251911987654',
                'email' => 'sales@megapaper.et',
                'address' => 'Merkato, Addis Ababa',
                'tin_number' => 'TIN-MPT-101',
                'is_customer' => false,
                'is_supplier' => true,
            ],
            [
                'name' => 'Nile Printing Supplies',
                'phone' => '+251922345678',
                'email' => 'orders@nilesupplies.et',
                'address' => 'Piassa, Addis Ababa',
                'tin_number' => 'TIN-NPS-102',
                'is_customer' => false,
                'is_supplier' => true,
            ],
            [
                'name' => 'Addis Ink & Chemicals',
                'phone' => '+251933456789',
                'email' => 'info@addisink.et',
                'address' => 'Akaki Kality, Addis Ababa',
                'tin_number' => 'TIN-AIC-103',
                'is_customer' => false,
                'is_supplier' => true,
            ],
            [
                'name' => 'Horn of Africa Paper Mill',
                'phone' => '+251944567890',
                'email' => 'supply@hornpapermill.et',
                'address' => 'Dire Dawa Industrial Zone',
                'tin_number' => 'TIN-HAP-104',
                'is_customer' => false,
                'is_supplier' => true,
            ],
            [
                'name' => 'Ethio Packaging Solutions',
                'phone' => '+251955678901',
                'email' => 'sales@ethiopack.et',
                'address' => 'Bole Lemi Industrial Park, Addis Ababa',
                'tin_number' => 'TIN-EPS-105',
                'is_customer' => false,
                'is_supplier' => true,
            ],
        ];

        foreach (array_merge($customers, $suppliers) as $partner) {
            Partner::updateOrCreate(
                ['tin_number' => $partner['tin_number']],
                $partner
            );
        }
    }
}
