<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Telebirr Agent',
                'description' => 'Telebirr agents who distribute mobile money services',
                'customer_count' => 0,
            ],
            [
                'name' => 'eVoucher',
                'description' => 'Customers dealing with electronic vouchers',
                'customer_count' => 0,
            ],
            [
                'name' => 'Walk-in',
                'description' => 'Walk-in customers visiting the branch',
                'customer_count' => 0,
            ],
            [
                'name' => 'SIM',
                'description' => 'Customers purchasing SIM cards',
                'customer_count' => 0,
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}