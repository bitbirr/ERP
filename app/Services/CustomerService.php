<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerAddress;
use Illuminate\Support\Str;

class CustomerService
{
    /**
     * Normalize phone number to E.164 format for Ethiopia (+251).
     */
    public function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // Handle Ethiopian phone numbers
        if (Str::startsWith($phone, '251')) {
            return '+251' . substr($phone, 3);
        }

        if (Str::startsWith($phone, '0')) {
            return '+251' . substr($phone, 1);
        }

        if (Str::startsWith($phone, '9') && strlen($phone) === 9) {
            return '+251' . $phone;
        }

        // If already in E.164 format
        if (Str::startsWith($phone, '+251')) {
            return $phone;
        }

        // For other formats, assume it's Ethiopian and add +251
        if (strlen($phone) === 9 && Str::startsWith($phone, '9')) {
            return '+251' . $phone;
        }

        return $phone; // Return as-is if can't normalize
    }

    /**
     * Validate Ethiopian address hierarchy.
     */
    public function validateEthiopianAddress(array $addressData): array
    {
        $errors = [];

        // Basic Ethiopian regions (simplified list)
        $validRegions = [
            'Addis Ababa', 'Afar', 'Amhara', 'Benishangul-Gumuz', 'Dire Dawa',
            'Gambella', 'Harari', 'Oromia', 'Somali', 'Southern Nations, Nationalities, and Peoples\' Region',
            'Tigray'
        ];

        if (!empty($addressData['region']) && !in_array($addressData['region'], $validRegions)) {
            $errors[] = 'Invalid region: ' . $addressData['region'];
        }

        // Basic woreda/kebele validation (would need more comprehensive data)
        if (!empty($addressData['woreda']) && strlen($addressData['woreda']) < 2) {
            $errors[] = 'Woreda name seems too short';
        }

        if (!empty($addressData['kebele']) && strlen($addressData['kebele']) < 2) {
            $errors[] = 'Kebele name seems too short';
        }

        return $errors;
    }

    /**
     * Create customer with normalized data.
     */
    public function createCustomer(array $data): Customer
    {
        // Normalize phone if provided
        if (!empty($data['phone'])) {
            $data['phone'] = $this->normalizePhoneNumber($data['phone']);
        }

        return Customer::create($data);
    }

    /**
     * Update customer with normalized data.
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        // Normalize phone if provided
        if (!empty($data['phone'])) {
            $data['phone'] = $this->normalizePhoneNumber($data['phone']);
        }

        $customer->update($data);
        return $customer;
    }

    /**
     * Add contact to customer with normalization.
     */
    public function addContact(Customer $customer, array $contactData): CustomerContact
    {
        // Normalize phone if it's a phone contact
        if ($contactData['type'] === 'phone' && !empty($contactData['value'])) {
            $contactData['value'] = $this->normalizePhoneNumber($contactData['value']);
        }

        return $customer->contacts()->create($contactData);
    }

    /**
     * Add address to customer with validation.
     */
    public function addAddress(Customer $customer, array $addressData): CustomerAddress
    {
        // Validate Ethiopian address
        $validationErrors = $this->validateEthiopianAddress($addressData);
        if (!empty($validationErrors)) {
            throw new \InvalidArgumentException('Address validation failed: ' . implode(', ', $validationErrors));
        }

        return $customer->addresses()->create($addressData);
    }

    /**
     * Search customers with advanced filters.
     */
    public function searchCustomers(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Customer::query();

        // Type filter
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Segment filter
        if (!empty($filters['segment_id'])) {
            $query->whereHas('segments', function ($q) use ($filters) {
                $q->where('id', $filters['segment_id']);
            });
        }

        // Region filter
        if (!empty($filters['region'])) {
            $query->whereHas('addresses', function ($q) use ($filters) {
                $q->where('region', $filters['region'])->where('is_primary', true);
            });
        }

        // Search query
        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%")
                  ->orWhereHas('contacts', function ($cq) use ($search) {
                      $cq->where('value', 'ilike', "%{$search}%");
                  });
            });
        }

        // Tag filter
        if (!empty($filters['tag'])) {
            $query->whereHas('tags', function ($q) use ($filters) {
                $q->where('name', $filters['tag']);
            });
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query->with(['contacts', 'addresses', 'tags', 'segments', 'category'])
                    ->orderBy('name')
                    ->paginate($filters['per_page'] ?? 50);
    }

    /**
     * Get customer statistics.
     */
    public function getCustomerStats(): array
    {
        return [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::where('is_active', true)->count(),
            'individual_customers' => Customer::where('type', 'individual')->count(),
            'organization_customers' => Customer::where('type', 'organization')->count(),
            'customers_with_phone' => Customer::whereNotNull('phone')->count(),
            'customers_with_email' => Customer::whereNotNull('email')->count(),
        ];
    }
}