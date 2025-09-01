<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $products = [
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'code' => 'SIM-PREP-4G', 'name' => 'SIM Card 4G', 'type' => 'SIM', 'uom' => 'pcs', 'is_active' => true, 'meta' => ['iccid_range' => '8901000000000000000-8901000000000009999']],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'code' => 'VCH-100', 'name' => 'Voucher 100', 'type' => 'VOUCHER', 'uom' => 'card', 'is_active' => true, 'meta' => ['serials' => ['VCH001', 'VCH002'], 'batch' => 'BATCH001']],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'code' => 'EVD-TOPUP', 'name' => 'EVD Topup', 'type' => 'EVD', 'uom' => 'amount', 'is_active' => true],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'code' => 'AIR-ET', 'name' => 'Ethio Telecom Airtime', 'type' => 'E_AIRTIME', 'uom' => 'amount', 'is_active' => true],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'code' => 'TB-CASHIN', 'name' => 'Telebirr Cash In', 'type' => 'TELEBIRR', 'uom' => 'amount', 'is_active' => true],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'code' => 'YIM-SVC', 'name' => 'Yimulu Service', 'type' => 'YIMULU', 'uom' => 'card', 'is_active' => true],
        ];

        foreach ($products as $product) {
            \App\Models\Product::firstOrCreate(['code' => $product['code']], $product);
        }

        // Create additional products via factory if needed
        \App\Models\Product::factory()->count(20)->create();
    }
}