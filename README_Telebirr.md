# Telebirr Integration Documentation

## Overview

The Telebirr integration provides a comprehensive API for managing Telebirr (Ethiopian mobile money) transactions within the ERP system. It supports agent management, transaction processing, reconciliation, and reporting functionalities.

## Features

- **Agent Management**: Create, update, and manage Telebirr agents
- **Transaction Processing**: Support for TOPUP, ISSUE, REPAY, and LOAN transactions
- **GL Integration**: Automatic posting to General Ledger with proper accounting entries
- **Reconciliation**: Transaction reconciliation and reporting
- **Idempotency**: Duplicate request prevention using idempotency keys
- **Audit Trail**: Complete audit logging for all operations

## Architecture

### Core Components

1. **TelebirrController**: Main API controller handling HTTP requests
2. **TelebirrService**: Business logic service for transaction processing
3. **Models**: TelebirrAgent, TelebirrTransaction, BankAccount
4. **Request Classes**: Validation and authorization for API endpoints

### Database Tables

- `telebirr_agents`: Agent information and status
- `telebirr_transactions`: Transaction records with GL journal references
- `bank_accounts`: Bank account information for settlements

## API Endpoints

### Authentication

All endpoints require authentication via Sanctum and appropriate capabilities.

### Agent Management

#### List Agents

```http
GET /api/telebirr/agents
```

**Query Parameters:**

- `status` (optional): Filter by status (Active/Inactive)
- `search` (optional): Search by name, short code, or phone
- `per_page` (optional): Items per page (default: 50)

**Response:**

```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Agent Name",
      "short_code": "AGT001",
      "phone": "+251911123456",
      "location": "Addis Ababa",
      "status": "Active",
      "created_at": "2024-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "total": 100,
    "per_page": 50,
    "current_page": 1,
    "last_page": 2
  }
}
```

#### Get Agent Details

```http
GET /api/telebirr/agents/{agent}
```

#### Create Agent

```http
POST /api/telebirr/agents
```

**Request Body:**

```json
{
  "name": "New Agent Corp",
  "short_code": "NAC001",
  "phone": "+251922123456",
  "location": "Addis Ababa",
  "status": "Active",
  "notes": "Optional notes"
}
```

**Required Capability:** `telebirr.manage`

#### Update Agent

```http
PATCH /api/telebirr/agents/{agent}
```

**Request Body:** (same as create, all fields optional)

**Required Capability:** `telebirr.manage`

### Transaction Management

#### List Transactions

```http
GET /api/telebirr/transactions
```

**Query Parameters:**

- `tx_type` (optional): Filter by transaction type
- `status` (optional): Filter by status
- `agent_id` (optional): Filter by agent
- `date_from` (optional): Start date
- `date_to` (optional): End date
- `search` (optional): Search by external reference or remarks
- `per_page` (optional): Items per page

#### Get Transaction Details

```http
GET /api/telebirr/transactions/{transaction}
```

#### Post TOPUP Transaction

```http
POST /api/telebirr/transactions/topup
```

**Request Body:**

```json
{
  "amount": 1000.00,
  "currency": "ETB",
  "idempotency_key": "unique-key-123",
  "bank_external_number": "BANK001",
  "external_ref": "REF123",
  "remarks": "Topup transaction"
}
```

**Required Capability:** `telebirr.post`

#### Post ISSUE Transaction

```http
POST /api/telebirr/transactions/issue
```

**Request Body:**

```json
{
  "amount": 500.00,
  "currency": "ETB",
  "idempotency_key": "issue-key-456",
  "agent_short_code": "AGT001",
  "external_ref": "ISSUE123",
  "remarks": "Issue e-float to agent"
}
```

**Required Capability:** `telebirr.post`

#### Post REPAY Transaction

```http
POST /api/telebirr/transactions/repay
```

**Request Body:**

```json
{
  "amount": 300.00,
  "currency": "ETB",
  "idempotency_key": "repay-key-789",
  "agent_short_code": "AGT001",
  "bank_external_number": "BANK001",
  "external_ref": "REPAY123",
  "remarks": "Agent repayment"
}
```

**Required Capability:** `telebirr.post`

#### Post LOAN Transaction

```http
POST /api/telebirr/transactions/loan
```

**Request Body:** (same as ISSUE transaction)

**Required Capability:** `telebirr.post`

#### Void Transaction

```http
PATCH /api/telebirr/transactions/{transaction}/void
```

**Required Capability:** `telebirr.void`

### Reconciliation

#### Get Reconciliation Data

```http
GET /api/telebirr/reconciliation
```

**Query Parameters:**

- `date_from` (required): Start date
- `date_to` (required): End date

**Response:**

```json
{
  "period": {
    "from": "2024-01-01",
    "to": "2024-01-31"
  },
  "summary": {
    "total_transactions": 150,
    "total_amount": 75000.00,
    "by_type": {
      "TOPUP": { "count": 50, "amount": 25000.00 },
      "ISSUE": { "count": 60, "amount": 30000.00 },
      "REPAY": { "count": 40, "amount": 20000.00 }
    }
  },
  "transactions": [...]
}
```

**Required Capability:** `telebirr.view`

### Reporting

#### Agent Balances Report

```http
GET /api/telebirr/reports/agent-balances
```

**Response:**

```json
{
  "data": [
    {
      "agent": {
        "id": "uuid",
        "name": "Agent Name",
        "short_code": "AGT001"
      },
      "outstanding_balance": 1500.00,
      "last_transaction": {
        "id": "uuid",
        "tx_type": "ISSUE",
        "amount": 500.00,
        "created_at": "2024-01-15T10:30:00Z"
      }
    }
  ],
  "generated_at": "2024-01-31T12:00:00Z"
}
```

#### Transaction Summary Report

```http
GET /api/telebirr/reports/transaction-summary
```

**Query Parameters:**

- `date_from` (required): Start date
- `date_to` (required): End date

**Response:**

```json
{
  "period": {
    "from": "2024-01-01",
    "to": "2024-01-31"
  },
  "totals": {
    "count": 150,
    "amount": 75000.00
  },
  "by_type": [
    {
      "tx_type": "TOPUP",
      "count": 50,
      "amount": 25000.00
    }
  ],
  "by_agent": [
    {
      "agent": {
        "name": "Agent Name",
        "short_code": "AGT001"
      },
      "count": 25,
      "amount": 12500.00
    }
  ]
}
```

## Transaction Types

### TOPUP

- **Purpose**: Add funds to distributor account from bank
- **GL Entries**:
  - Debit: Bank Account
  - Credit: Distributor Cash Account

### ISSUE

- **Purpose**: Issue e-float to agent
- **GL Entries**:
  - Debit: Agent Receivable Account
  - Credit: Distributor Cash Account

### REPAY

- **Purpose**: Agent repayment of outstanding balance
- **GL Entries**:
  - Debit: Distributor Cash Account
  - Credit: Bank Account

### LOAN

- **Purpose**: Issue loan e-float to agent (same as ISSUE)
- **GL Entries**: Same as ISSUE transaction

## Configuration

### GL Account Configuration

Configure posting rules in `config/telebirr_postings.php`:

```php
return [
    'TOPUP' => [
        'debit_account' => '1001',  // Bank account
        'credit_account' => '1101', // Distributor cash
    ],
    'ISSUE' => [
        'debit_account' => '1301',  // Agent receivable
        'credit_account' => '1101', // Distributor cash
    ],
    'REPAY' => [
        'debit_account' => '1101', // Distributor cash
        'credit_account' => '1001', // Bank account
    ],
];
```

## Error Handling

### Common Error Responses

#### Validation Errors (422)

```json
{
  "message": "Validation failed",
  "errors": {
    "amount": ["Amount must be positive"],
    "idempotency_key": ["Idempotency key is required"]
  }
}
```

#### Business Logic Errors (400)

```json
{
  "message": "Failed to post transaction",
  "error": "Agent not found: INVALID"
}
```

#### Authorization Errors (403)

```json
{
  "message": "This action is unauthorized."
}
```

## Idempotency

All transaction endpoints support idempotency using the `idempotency_key` field. If a request with the same key is submitted multiple times, only the first request will be processed, and subsequent requests will return the original transaction.

## Testing

### Unit Tests

Run unit tests for service methods:

```bash
php artisan test tests/Unit/TelebirrServiceTest.php
```

### Feature Tests

Run API endpoint tests:

```bash
php artisan test tests/Feature/TelebirrApiTest.php
```

### Test Data

Use the following factories for testing:

- `TelebirrAgent::factory()`
- `TelebirrTransaction::factory()`
- `BankAccount::factory()`

## Security Considerations

1. **Authentication**: All endpoints require valid Sanctum tokens
2. **Authorization**: Users must have appropriate capabilities
3. **Input Validation**: All inputs are validated using Form Request classes
4. **Idempotency**: Prevents duplicate transaction processing
5. **Audit Logging**: All operations are logged for compliance

## Monitoring and Logging

### Audit Logs

All Telebirr operations are logged with the following information:

- User ID performing the action
- Action type (create, update, post, void)
- Subject type and ID
- Old and new values (for updates)
- Timestamp

### GL Integration

All transactions automatically create GL journal entries for proper accounting integration.

## Troubleshooting

### Common Issues

1. **Agent Not Found**: Ensure agent short code is correct and agent is active
2. **Bank Account Not Found**: Verify bank external number exists and is active
3. **GL Posting Failures**: Check GL account configuration and balances
4. **Idempotency Conflicts**: Use unique idempotency keys for each transaction

### Debug Mode

Enable debug logging in `.env`:

```env
LOG_LEVEL=debug
```

## Future Enhancements

- Real-time transaction notifications
- Bulk transaction processing
- Advanced reconciliation features
- Integration with external Telebirr APIs
- Mobile app integration
- Enhanced reporting with charts and analytics

## Support

For technical support or questions about the Telebirr integration:

- Check the audit logs for detailed error information
- Review the test cases for usage examples
- Contact the development team for assistance

---

**Version:** 1.0.0
**Last Updated:** 2024-01-31
**Authors:** Development Team
