<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_category()
    {
        $category = Category::create([
            'name' => 'Test Category',
            'description' => 'Test description',
        ]);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals('Test Category', $category->name);
        $this->assertEquals('Test description', $category->description);
        $this->assertEquals(0, $category->customer_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_uuid_primary_key()
    {
        $category = Category::create([
            'name' => 'Test Category',
        ]);

        $this->assertNotNull($category->id);
        $this->assertIsString($category->id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $category->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_fillable_attributes()
    {
        $category = Category::create([
            'name' => 'Test Category',
            'description' => 'Test description',
            'customer_count' => 5,
        ]);

        $this->assertEquals('Test Category', $category->name);
        $this->assertEquals('Test description', $category->description);
        $this->assertEquals(5, $category->customer_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_correct_casts()
    {
        $category = Category::create([
            'name' => 'Test Category',
            'customer_count' => '10', // String input
        ]);

        $this->assertIsInt($category->customer_count);
        $this->assertEquals(10, $category->customer_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_unique_name_constraint()
    {
        Category::create(['name' => 'Unique Category']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Category::create(['name' => 'Unique Category']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_have_customers_relationship()
    {
        $category = Category::create(['name' => 'Test Category']);

        $customer1 = Customer::create([
            'name' => 'Customer 1',
            'type' => 'individual',
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $customer2 = Customer::create([
            'name' => 'Customer 2',
            'type' => 'individual',
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $customers = $category->customers;

        $this->assertCount(2, $customers);
        $this->assertEquals('Customer 1', $customers->first()->name);
        $this->assertEquals('Customer 2', $customers->last()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_scope_categories_with_customers()
    {
        $emptyCategory = Category::create(['name' => 'Empty Category']);
        $fullCategory = Category::create(['name' => 'Full Category', 'customer_count' => 3]);

        $categoriesWithCustomers = Category::withCustomers()->get();

        $this->assertCount(1, $categoriesWithCustomers);
        $this->assertEquals('Full Category', $categoriesWithCustomers->first()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_scope_categories_without_customers()
    {
        $emptyCategory = Category::create(['name' => 'Empty Category']);
        $fullCategory = Category::create(['name' => 'Full Category', 'customer_count' => 3]);

        $categoriesWithoutCustomers = Category::withoutCustomers()->get();

        $this->assertCount(1, $categoriesWithoutCustomers);
        $this->assertEquals('Empty Category', $categoriesWithoutCustomers->first()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_validation_rules()
    {
        $rules = Category::rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertArrayHasKey('customer_count', $rules);

        // Check that name rules contain the expected validation strings
        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:255', $rules['name']);
        $this->assertStringContainsString('unique:customer_categories,name', $rules['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_timestamps()
    {
        $category = Category::create(['name' => 'Test Category']);

        $this->assertNotNull($category->created_at);
        $this->assertNotNull($category->updated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $category->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $category->updated_at);
    }
}