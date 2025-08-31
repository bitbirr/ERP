# Telebirr Testing Guide

This document provides comprehensive instructions for seeding, running tests, understanding the role matrix, and avoiding common pitfalls when testing the Telebirr module.

## Seeding Telebirr Data

### Prerequisites
- Ensure the database is set up and migrations have been run
- Confirm that the `telebirr_agents` and `telebirr_transactions` tables exist

### Running Seeders

1. **Seed Telebirr Agents:**
   ```bash
   php artisan db:seed --class=TelebirrAgentsSeeder
   ```
   This creates 10+ sample agents with various statuses (Active, Inactive, Dormant) across different Ethiopian cities including Addis Ababa, Dire Dawa, Hawassa, Mekelle, Bahir Dar, Gondar, Jimma, Dessie, Shashemene, and Adama.

2. **Seed Telebirr GL Accounts:**
   ```bash
   php artisan db:seed --class=TelebirrGlAccountsSeeder
   ```
   This creates essential GL accounts for Telebirr operations including:
   - Bank accounts (CBE, EBIRR, Coopay, Abasiniya, Awash, Dashin, Telebirr, Esahal)
   - Telebirr Distributor liability account
   - AR - Agents receivable account
   - AR - EBIRR clearing account

3. **Run All Seeders:**
   ```bash
   php artisan db:seed
   ```
   This runs all seeders including the Telebirr-specific ones.

### Sample Data Created
- **Agents:** 13 agents with unique short codes (ADM001, DDR002, etc.)
- **GL Accounts:** 9 bank accounts + 3 Telebirr-specific accounts
- **Statuses:** Mix of Active, Inactive, and Dormant agents for comprehensive testing

## Running Tests

### Test Structure
Telebirr tests are organized in `tests/Feature/Telebirr/`:
- `PreRequestValidationTest.php` - Input validation and edge cases
- `TransactionalSideEffectsTest.php` - Business logic and side effects

### Running All Telebirr Tests
```bash
php artisan test tests/Feature/Telebirr/
```

### Running Specific Tests
```bash
# Run validation tests
php artisan test tests/Feature/Telebirr/PreRequestValidationTest.php

# Run side effects tests
php artisan test tests/Feature/Telebirr/TransactionalSideEffectsTest.php

# Run with verbose output
php artisan test tests/Feature/Telebirr/ --verbose
```

### Running Tests with Coverage
```bash
# Generate HTML coverage report
php artisan test tests/Feature/Telebirr/ --coverage-html=reports/coverage

# Generate coverage for specific classes
php artisan test --coverage --min=80
```

### Test Prerequisites
- Database must be seeded with Telebirr data
- User with appropriate capabilities must be authenticated
- GL accounts must be properly configured

## Role Matrix

The Telebirr module uses a capability-based access control system. Below is the role matrix:

| Role | Capabilities | Access Level | Description |
|------|-------------|-------------|-------------|
| **admin** | All `telebirr.*` | Full Access | Complete CRUD operations on agents and transactions, all reports |
| **manager** | `telebirr.view` | Read Only | View agents, transactions, and reports |
| **telebirr_distributor** | `telebirr.view`, `telebirr.post`, `telebirr.void` | Transaction Ops | Create/issue/loan/repay/topup transactions, void transactions |
| **sales** | `telebirr.view` | Read Only | View agents and transactions |
| **finance** | `telebirr.view`, `telebirr.post`, `telebirr.void` | Financial Ops | Create transactions, void transactions, GL journal access |
| **inventory** | None | No Access | Cannot access any Telebirr functionality |
| **audit** | None (GL view only) | Limited | Can view GL journals but no Telebirr-specific access |

### Key Capabilities
- `telebirr.view` - Read access to agents, transactions, reports
- `telebirr.post` - Create transactions (topup, issue, loan, repay)
- `telebirr.void` - Void/cancel transactions
- `telebirr.manage` - Create/update agents

## Common Gotchas and Troubleshooting

### 1. Idempotency Key Conflicts
**Issue:** Duplicate idempotency keys cause transaction failures
**Solution:** Ensure each transaction uses a unique idempotency key
**Test:** Check for `idempotency_conflict` exceptions

### 2. Agent Short Code Uniqueness
**Issue:** Duplicate short codes when creating agents
**Solution:** Use unique short codes (handled by database constraints)
**Test:** Attempt creating agents with existing short codes

### 3. GL Account Setup
**Issue:** Transactions fail without proper GL account configuration
**Solution:** Run `TelebirrGlAccountsSeeder` before testing transactions
**Test:** Verify GL accounts exist before running transaction tests

### 4. Capability Authorization
**Issue:** 403 Forbidden errors due to missing capabilities
**Solution:** Assign appropriate capabilities to test users
**Test:** Use `UserPolicy` to grant capabilities like `telebirr.post`

### 5. Database Transaction Rollbacks
**Issue:** Test data persists between test runs
**Solution:** Use `DatabaseTransactions` trait or manual cleanup
**Test:** Verify database state after failed transactions

### 6. Agent Status Validation
**Issue:** Transactions on inactive/dormant agents
**Solution:** Check agent status before processing
**Test:** Attempt transactions on agents with different statuses

### 7. Amount Validation
**Issue:** Invalid amounts (negative, zero, exceeding limits)
**Solution:** Validate amounts in request classes
**Test:** Test with various amount values including edge cases

### 8. Currency Handling
**Issue:** Incorrect currency processing
**Solution:** Ensure ETB currency is properly handled
**Test:** Verify currency field in transaction payloads

### 9. Audit Logging
**Issue:** Missing audit trails for transactions
**Solution:** Check `AuditLogger` integration
**Test:** Verify audit logs are created for all transaction types

### 10. Journal Reversal
**Issue:** Voiding transactions doesn't reverse GL entries
**Solution:** Ensure `reverseJournal` method is called
**Test:** Check GL journal status after voiding transactions

## Test Data Setup

For consistent testing, use these sample values:

```php
// Sample agent data
$agentData = [
    'name' => 'Test Agent Corp',
    'short_code' => 'TST001',
    'phone' => '+251911123456',
    'location' => 'Addis Ababa',
    'status' => 'Active'
];

// Sample transaction data
$transactionData = [
    'agent_short_code' => 'TST001',
    'amount' => 1000.00,
    'currency' => 'ETB',
    'idempotency_key' => 'test-key-' . uniqid()
];
```

## Performance Considerations

- Use database transactions in tests to avoid data pollution
- Mock external services when testing Telebirr integrations
- Run tests in parallel when possible using `--parallel`
- Monitor memory usage for large test suites

## Integration Testing

When testing with external systems:
- Mock EBIRR API responses
- Use test-specific idempotency keys
- Verify webhook payloads if applicable
- Test timeout scenarios

This guide should help you effectively test the Telebirr module while avoiding common pitfalls and ensuring comprehensive coverage.