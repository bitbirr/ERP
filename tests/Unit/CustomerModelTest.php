<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerAddress;
use App\Models\CustomerNote;
use App\Models\CustomerTag;
use App\Models\CustomerSegment;
use App\Models\CustomerInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_fillable_attributes()
    {
        $customer = new Customer();

        $expectedFillable = [
            'type',
            'name',
            'email',
            'phone',
            'tax_id',
            'description',
            'is_active',
            'metadata',
        ];

        $this->assertEquals($expectedFillable, $customer->getFillable());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_correct_casts()
    {
        $customer = new Customer();

        $expectedCasts = [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];

        $this->assertEquals($expectedCasts, $customer->getCasts());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_uuids()
    {
        $customer = Customer::factory()->create();

        $this->assertNotNull($customer->id);
        $this->assertIsString($customer->id);
        // UUID should be 36 characters long
        $this->assertEquals(36, strlen($customer->id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_contacts_relationship()
    {
        $customer = Customer::factory()->create();

        // Create some contacts
        CustomerContact::factory()->create(['customer_id' => $customer->id]);
        CustomerContact::factory()->create(['customer_id' => $customer->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $customer->contacts());
        $this->assertCount(2, $customer->contacts);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_addresses_relationship()
    {
        $customer = Customer::factory()->create();

        // Create some addresses
        CustomerAddress::factory()->create(['customer_id' => $customer->id]);
        CustomerAddress::factory()->create(['customer_id' => $customer->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $customer->addresses());
        $this->assertCount(2, $customer->addresses);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_notes_relationship()
    {
        $customer = Customer::factory()->create();

        // Create some notes
        CustomerNote::factory()->create(['customer_id' => $customer->id]);
        CustomerNote::factory()->create(['customer_id' => $customer->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $customer->notes());
        $this->assertCount(2, $customer->notes);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_tags_relationship()
    {
        $customer = Customer::factory()->create();

        // Create some tags
        CustomerTag::factory()->create(['customer_id' => $customer->id]);
        CustomerTag::factory()->create(['customer_id' => $customer->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $customer->tags());
        $this->assertCount(2, $customer->tags);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_has_many_interactions_relationship()
    {
        $customer = Customer::factory()->create();

        // Create some interactions
        CustomerInteraction::factory()->create(['customer_id' => $customer->id]);
        CustomerInteraction::factory()->create(['customer_id' => $customer->id]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $customer->interactions());
        $this->assertCount(2, $customer->interactions);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_belongs_to_many_segments_relationship()
    {
        $customer = Customer::factory()->create();
        $segment1 = CustomerSegment::factory()->create();
        $segment2 = CustomerSegment::factory()->create();

        // Create pivot records manually due to UUID primary key in pivot table
        DB::table('customer_segment_assignments')->insert([
            ['id' => (string) Str::uuid(), 'customer_id' => $customer->id, 'segment_id' => $segment1->id, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'customer_id' => $customer->id, 'segment_id' => $segment2->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $customer->segments());
        $this->assertCount(2, $customer->segments);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_primary_contact()
    {
        $customer = Customer::factory()->create();

        // Create contacts - one primary, one not
        CustomerContact::factory()->create([
            'customer_id' => $customer->id,
            'is_primary' => false,
            'type' => 'email',
            'value' => 'secondary@example.com'
        ]);

        $primaryContact = CustomerContact::factory()->create([
            'customer_id' => $customer->id,
            'is_primary' => true,
            'type' => 'email',
            'value' => 'primary@example.com'
        ]);

        $this->assertEquals($primaryContact->id, $customer->primaryContact()->id);
        $this->assertEquals('primary@example.com', $customer->primaryContact()->value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_when_no_primary_contact()
    {
        $customer = Customer::factory()->create();

        // Create non-primary contact
        CustomerContact::factory()->create([
            'customer_id' => $customer->id,
            'is_primary' => false
        ]);

        $this->assertNull($customer->primaryContact());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_primary_address()
    {
        $customer = Customer::factory()->create();

        // Create addresses - one primary, one not
        CustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'is_primary' => false,
            'street_address' => 'Secondary Address'
        ]);

        $primaryAddress = CustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'is_primary' => true,
            'street_address' => 'Primary Address'
        ]);

        $this->assertEquals($primaryAddress->id, $customer->primaryAddress()->id);
        $this->assertEquals('Primary Address', $customer->primaryAddress()->street_address);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_when_no_primary_address()
    {
        $customer = Customer::factory()->create();

        // Create non-primary address
        CustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'is_primary' => false
        ]);

        $this->assertNull($customer->primaryAddress());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_scope_active_customers()
    {
        Customer::factory()->create(['is_active' => true]);
        Customer::factory()->create(['is_active' => true]);
        Customer::factory()->create(['is_active' => false]);

        $activeCustomers = Customer::active()->get();

        $this->assertCount(2, $activeCustomers);
        $activeCustomers->each(function ($customer) {
            $this->assertTrue($customer->is_active);
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_scope_individual_customers()
    {
        Customer::factory()->create(['type' => 'individual']);
        Customer::factory()->create(['type' => 'individual']);
        Customer::factory()->create(['type' => 'organization']);

        $individualCustomers = Customer::individuals()->get();

        $this->assertCount(2, $individualCustomers);
        $individualCustomers->each(function ($customer) {
            $this->assertEquals('individual', $customer->type);
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_scope_organization_customers()
    {
        Customer::factory()->create(['type' => 'organization']);
        Customer::factory()->create(['type' => 'organization']);
        Customer::factory()->create(['type' => 'individual']);

        $organizationCustomers = Customer::organizations()->get();

        $this->assertCount(2, $organizationCustomers);
        $organizationCustomers->each(function ($customer) {
            $this->assertEquals('organization', $customer->type);
        });
    }
}