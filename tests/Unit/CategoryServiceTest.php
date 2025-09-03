<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Customer;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CategoryService::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_category()
    {
        $categoryData = [
            'name' => 'Premium Customers',
            'description' => 'High-value customers',
        ];

        $category = $this->service->createCategory($categoryData);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals('Premium Customers', $category->name);
        $this->assertEquals('High-value customers', $category->description);
        $this->assertEquals(0, $category->customer_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_category_without_description()
    {
        $categoryData = [
            'name' => 'Basic Customers',
        ];

        $category = $this->service->createCategory($categoryData);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals('Basic Customers', $category->name);
        $this->assertNull($category->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_validation_exception_for_duplicate_name()
    {
        Category::create([
            'name' => 'Test Category',
            'description' => 'Test description',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->createCategory([
            'name' => 'Test Category',
            'description' => 'Another description',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_category()
    {
        $category = Category::create([
            'name' => 'Old Name',
            'description' => 'Old description',
        ]);

        $updateData = [
            'name' => 'New Name',
            'description' => 'New description',
        ];

        $updatedCategory = $this->service->updateCategory($category, $updateData);

        $this->assertEquals('New Name', $updatedCategory->name);
        $this->assertEquals('New description', $updatedCategory->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_delete_category_without_customers()
    {
        $category = Category::create([
            'name' => 'Empty Category',
            'description' => 'No customers',
        ]);

        $result = $this->service->deleteCategory($category);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('customer_categories', ['id' => $category->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_when_deleting_category_with_customers()
    {
        $category = Category::create([
            'name' => 'Category with Customers',
            'description' => 'Has customers',
            'customer_count' => 1,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot delete category with assigned customers');

        $this->service->deleteCategory($category);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_category_by_id()
    {
        $category = Category::create([
            'name' => 'Test Category',
            'description' => 'Test description',
        ]);

        $foundCategory = $this->service->getCategoryById($category->id);

        $this->assertInstanceOf(Category::class, $foundCategory);
        $this->assertEquals($category->id, $foundCategory->id);
        $this->assertEquals('Test Category', $foundCategory->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_nonexistent_category()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->getCategoryById('550e8400-e29b-41d4-a716-446655440000'); // Valid UUID format but doesn't exist
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_categories_with_pagination()
    {
        Category::create(['name' => 'Category A']);
        Category::create(['name' => 'Category B']);
        Category::create(['name' => 'Category C']);

        $categories = $this->service->getCategories(['per_page' => 2]);

        $this->assertCount(2, $categories);
        $this->assertEquals(3, $categories->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_search_categories()
    {
        Category::create(['name' => 'Premium Customers', 'description' => 'High value']);
        Category::create(['name' => 'Basic Customers', 'description' => 'Regular customers']);
        Category::create(['name' => 'VIP Customers', 'description' => 'Very important']);

        $results = $this->service->getCategories(['q' => 'Premium']);
        $this->assertCount(1, $results);
        $this->assertEquals('Premium Customers', $results->first()->name);

        $results = $this->service->getCategories(['q' => 'customers']);
        $this->assertCount(3, $results);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_assign_customer_to_category()
    {
        $category = Category::create(['name' => 'Test Category']);
        $customer = Customer::create([
            'name' => 'Test Customer',
            'type' => 'individual',
            'is_active' => true,
        ]);

        $assignedCustomer = $this->service->assignCustomerToCategory($customer->id, $category->id);

        $this->assertEquals($category->id, $assignedCustomer->category_id);
        $category->refresh();
        $this->assertEquals(1, $category->customer_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_move_customer_from_one_category_to_another()
    {
        $oldCategory = Category::create(['name' => 'Old Category']);
        $newCategory = Category::create(['name' => 'New Category']);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'type' => 'individual',
            'is_active' => true,
            'category_id' => $oldCategory->id,
        ]);

        $oldCategory->increment('customer_count');

        $movedCustomer = $this->service->assignCustomerToCategory($customer->id, $newCategory->id);

        $this->assertEquals($newCategory->id, $movedCustomer->category_id);

        $oldCategory->refresh();
        $newCategory->refresh();

        $this->assertEquals(0, $oldCategory->customer_count);
        $this->assertEquals(1, $newCategory->customer_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_remove_customer_from_category()
    {
        $category = Category::create(['name' => 'Test Category']);
        $customer = Customer::create([
            'name' => 'Test Customer',
            'type' => 'individual',
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $category->increment('customer_count');

        $removedCustomer = $this->service->removeCustomerFromCategory($customer->id);

        $this->assertNull($removedCustomer->category_id);

        $category->refresh();
        $this->assertEquals(0, $category->customer_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_when_removing_customer_not_in_category()
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'type' => 'individual',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Customer is not assigned to any category');

        $this->service->removeCustomerFromCategory($customer->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_category_statistics()
    {
        Category::create(['name' => 'Empty Category']);
        Category::create(['name' => 'Category with Customers', 'customer_count' => 5]);
        Category::create(['name' => 'Another with Customers', 'customer_count' => 3]);

        $stats = $this->service->getCategoryStats();

        $this->assertEquals(3, $stats['total_categories']);
        $this->assertEquals(2, $stats['categories_with_customers']);
        $this->assertEquals(1, $stats['categories_without_customers']);
        $this->assertEquals(8, $stats['total_customers_categorized']);
    }
}