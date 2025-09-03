<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerAddress;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerService::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_normalize_ethiopian_phone_numbers()
    {
        // Test phone starting with 251
        $this->assertEquals('+251911111111', $this->service->normalizePhoneNumber('251911111111'));

        // Test phone starting with 0
        $this->assertEquals('+251911111111', $this->service->normalizePhoneNumber('0911111111'));

        // Test 9-digit phone
        $this->assertEquals('+251911111111', $this->service->normalizePhoneNumber('911111111'));

        // Test already normalized phone
        $this->assertEquals('+251911111111', $this->service->normalizePhoneNumber('+251911111111'));

        // Test non-Ethiopian phone (should return as-is)
        $this->assertEquals('1234567890', $this->service->normalizePhoneNumber('1234567890'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_validate_ethiopian_addresses()
    {
        // Valid address
        $validAddress = [
            'region' => 'Addis Ababa',
            'woreda' => 'Bole',
            'kebele' => '01'
        ];
        $this->assertEmpty($this->service->validateEthiopianAddress($validAddress));

        // Invalid region
        $invalidAddress = [
            'region' => 'Invalid Region',
            'woreda' => 'Test',
            'kebele' => '01'
        ];
        $errors = $this->service->validateEthiopianAddress($invalidAddress);
        $this->assertContains('Invalid region: Invalid Region', $errors);

        // Short woreda
        $shortWoreda = [
            'region' => 'Addis Ababa',
            'woreda' => 'A',
            'kebele' => '01'
        ];
        $errors = $this->service->validateEthiopianAddress($shortWoreda);
        $this->assertContains('Woreda name seems too short', $errors);

        // Short kebele
        $shortKebele = [
            'region' => 'Addis Ababa',
            'woreda' => 'Bole',
            'kebele' => 'A'
        ];
        $errors = $this->service->validateEthiopianAddress($shortKebele);
        $this->assertContains('Kebele name seems too short', $errors);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_customer()
    {
        $customerData = [
            'type' => 'individual',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '0911111111',
            'is_active' => true,
        ];

        $customer = $this->service->createCustomer($customerData);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('individual', $customer->type);
        $this->assertEquals('John Doe', $customer->name);
        $this->assertEquals('john@example.com', $customer->email);
        $this->assertEquals('+251911111111', $customer->phone); // Normalized
        $this->assertTrue($customer->is_active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_customer_without_phone()
    {
        $customerData = [
            'type' => 'organization',
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'is_active' => true,
        ];

        $customer = $this->service->createCustomer($customerData);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('organization', $customer->type);
        $this->assertEquals('Test Company', $customer->name);
        $this->assertNull($customer->phone);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_customer()
    {
        $customer = Customer::factory()->create([
            'name' => 'Old Name',
            'phone' => '0911111111',
        ]);

        $updateData = [
            'name' => 'New Name',
            'phone' => '0922222222',
        ];

        $updatedCustomer = $this->service->updateCustomer($customer, $updateData);

        $this->assertEquals('New Name', $updatedCustomer->name);
        $this->assertEquals('+251922222222', $updatedCustomer->phone); // Normalized
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_add_contact_to_customer()
    {
        $customer = Customer::factory()->create();

        $contactData = [
            'type' => 'phone',
            'value' => '0911111111',
            'is_primary' => true,
        ];

        $contact = $this->service->addContact($customer, $contactData);

        $this->assertInstanceOf(CustomerContact::class, $contact);
        $this->assertEquals('phone', $contact->type);
        $this->assertEquals('+251911111111', $contact->value); // Normalized
        $this->assertTrue($contact->is_primary);
        $this->assertEquals($customer->id, $contact->customer_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_add_email_contact_without_normalization()
    {
        $customer = Customer::factory()->create();

        $contactData = [
            'type' => 'email',
            'value' => 'test@example.com',
            'is_primary' => false,
        ];

        $contact = $this->service->addContact($customer, $contactData);

        $this->assertEquals('email', $contact->type);
        $this->assertEquals('test@example.com', $contact->value); // Not normalized
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_add_address_to_customer()
    {
        $customer = Customer::factory()->create();

        $addressData = [
            'type' => 'billing',
            'region' => 'Addis Ababa',
            'woreda' => 'Bole',
            'kebele' => '01',
            'street_address' => '123 Main St',
            'is_primary' => true,
        ];

        $address = $this->service->addAddress($customer, $addressData);

        $this->assertInstanceOf(CustomerAddress::class, $address);
        $this->assertEquals('billing', $address->type);
        $this->assertEquals('Addis Ababa', $address->region);
        $this->assertTrue($address->is_primary);
        $this->assertEquals($customer->id, $address->customer_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_exception_for_invalid_address()
    {
        $customer = Customer::factory()->create();

        $invalidAddressData = [
            'region' => 'Invalid Region',
            'woreda' => 'Test',
            'kebele' => '01',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address validation failed');

        $this->service->addAddress($customer, $invalidAddressData);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_search_customers()
    {
        Customer::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'type' => 'individual',
            'is_active' => true,
        ]);

        Customer::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'type' => 'individual',
            'is_active' => true,
        ]);

        // Search by name
        $results = $this->service->searchCustomers(['q' => 'John']);
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);

        // Search by email
        $results = $this->service->searchCustomers(['q' => 'jane@example.com']);
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results->first()->name);

        // Filter by type
        $results = $this->service->searchCustomers(['type' => 'individual']);
        $this->assertCount(2, $results);

        // Filter by active status
        $results = $this->service->searchCustomers(['is_active' => true]);
        $this->assertCount(2, $results);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_customer_statistics()
    {
        // Ensure clean state by counting existing customers
        $existingCount = Customer::count();

        Customer::factory()->create(['type' => 'individual', 'is_active' => true, 'phone' => '0911111111', 'email' => 'test@example.com']);
        Customer::factory()->create(['type' => 'organization', 'is_active' => false]);
        Customer::factory()->create(['type' => 'individual', 'is_active' => true]);

        $stats = $this->service->getCustomerStats();

        $this->assertEquals($existingCount + 3, $stats['total_customers']);
        $this->assertEquals($existingCount + 2, $stats['active_customers']);
        $this->assertEquals($existingCount + 2, $stats['individual_customers']);
        $this->assertEquals($existingCount + 1, $stats['organization_customers']);
        $this->assertGreaterThanOrEqual(1, $stats['customers_with_phone']);
        $this->assertGreaterThanOrEqual(1, $stats['customers_with_email']);
    }
}