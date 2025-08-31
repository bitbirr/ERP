<?php

namespace Database\Seeders;

use App\Models\TelebirrAgent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TelebirrAgentsSeeder extends Seeder
{
    public function run(): void
    {
        // Create 8-12 agents with varied statuses and locations
        $agents = [
            [
                'name' => 'Addis Ababa Main Agent',
                'short_code' => 'ADM001',
                'phone' => '+251911123456',
                'location' => 'Addis Ababa',
                'status' => 'Active',
                'notes' => 'Main distributor agent',
            ],
            [
                'name' => 'Dire Dawa Branch',
                'short_code' => 'DDR002',
                'phone' => '+251911234567',
                'location' => 'Dire Dawa',
                'status' => 'Active',
                'notes' => 'Regional branch',
            ],
            [
                'name' => 'Hawassa Outlet',
                'short_code' => 'HWS003',
                'phone' => '+251911345678',
                'location' => 'Hawassa',
                'status' => 'Dormant',
                'notes' => 'Seasonal outlet',
            ],
            [
                'name' => 'Mekelle Center',
                'short_code' => 'MKL004',
                'phone' => '+251911456789',
                'location' => 'Mekelle',
                'status' => 'Active',
                'notes' => 'Northern region hub',
            ],
            [
                'name' => 'Bahir Dar Point',
                'short_code' => 'BDR005',
                'phone' => '+251911567890',
                'location' => 'Bahir Dar',
                'status' => 'Inactive',
                'notes' => 'Under maintenance',
            ],
            [
                'name' => 'Gondar Station',
                'short_code' => 'GDR006',
                'phone' => '+251911678901',
                'location' => 'Gondar',
                'status' => 'Active',
                'notes' => 'Historical city outlet',
            ],
            [
                'name' => 'Jimma Hub',
                'short_code' => 'JMM007',
                'phone' => '+251911789012',
                'location' => 'Jimma',
                'status' => 'Dormant',
                'notes' => 'Coffee region agent',
            ],
            [
                'name' => 'Dessie Branch',
                'short_code' => 'DSS008',
                'phone' => '+251911890123',
                'location' => 'Dessie',
                'status' => 'Active',
                'notes' => 'Textile industry area',
            ],
            [
                'name' => 'Shashemene Outlet',
                'short_code' => 'SHM009',
                'phone' => '+251911901234',
                'location' => 'Shashemene',
                'status' => 'Active',
                'notes' => 'Agricultural hub',
            ],
            [
                'name' => 'Adama Center',
                'short_code' => 'ADM010',
                'phone' => '+251911012345',
                'location' => 'Adama',
                'status' => 'Inactive',
                'notes' => 'Industrial zone',
            ],
            // Edge case: different status
            [
                'name' => 'Edge Case Agent',
                'short_code' => 'EDG011',
                'phone' => '+251911999999',
                'location' => 'Unknown',
                'status' => 'Dormant',
                'notes' => 'Test agent with dormant status',
            ],
            // Edge case: duplicate short code attempt (will be handled by DB constraint)
            [
                'name' => 'Duplicate Code Agent',
                'short_code' => 'ADM001', // Same as first
                'phone' => '+251911123457',
                'location' => 'Addis Ababa',
                'status' => 'Active',
                'notes' => 'Attempt duplicate short code',
            ],
        ];

        foreach ($agents as $agentData) {
            TelebirrAgent::firstOrCreate(
                ['short_code' => $agentData['short_code']],
                $agentData
            );
        }

        // Create additional random agents manually
        $additionalAgents = [
            [
                'name' => 'Random Agent 1',
                'short_code' => 'RND012',
                'phone' => '+251911111111',
                'location' => 'Random City',
                'status' => 'Active',
                'notes' => 'Random agent',
            ],
            [
                'name' => 'Random Agent 2',
                'short_code' => 'RND013',
                'phone' => '+251911222222',
                'location' => 'Another City',
                'status' => 'Inactive',
                'notes' => 'Another random agent',
            ],
        ];

        foreach ($additionalAgents as $agentData) {
            TelebirrAgent::firstOrCreate(
                ['short_code' => $agentData['short_code']],
                $agentData
            );
        }
    }
}