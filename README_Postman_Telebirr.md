# Telebirr API Postman Collection & Environments

This repository contains a comprehensive Postman collection and environment files for testing the Telebirr API endpoints.

## Files Included

### Collection
- `Telebirr.postman_collection.json` - Main collection with all API endpoints organized by folders

### Environments
- `Telebirr_Admin.postman_environment.json` - Admin environment (all capabilities)
- `Telebirr_Manager.postman_environment.json` - Manager environment (read-only capabilities)
- `Telebirr_Distributor.postman_environment.json` - Distributor environment (Telebirr operations)
- `Telebirr_Finance.postman_environment.json` - Finance environment (GL and Telebirr operations)

## Setup Instructions

### 1. Import Collection and Environments
1. Open Postman
2. Click "Import" button
3. Import the collection file: `Telebirr.postman_collection.json`
4. Import the environment files for the roles you want to test

### 2. Configure Environment Variables
Before running requests, update the token values in each environment:

1. Select an environment from the dropdown
2. Click the "Environment quick look" icon (eye icon)
3. Update the `token` variable with actual Bearer tokens from your Laravel application

### 3. Getting Bearer Tokens
To get actual tokens for testing:

1. Start your Laravel application
2. Use the seeder users to authenticate:
   - Admin: `admin@example.com` / `secret123`
   - Manager: `manager@example.com` / `secret123`
   - Distributor: `distributor@example.com` / `secret123`
   - Finance: `finance@example.com` / `secret123`

3. Make a POST request to `{{baseURL}}/sanctum/token` with:
   ```json
   {
     "email": "admin@example.com",
     "password": "secret123",
     "device_name": "postman"
   }
   ```

4. Copy the returned `token` value and update the `token` environment variable

**Note**: The Sanctum token route has been added to `routes/api.php`. If you get a 404, make sure your Laravel application is running and the routes are cached (`php artisan route:cache`).

## Collection Structure

### Folders
- **Agents** - Agent management endpoints
- **Transactions** - Transaction operations (Topup, Issue, Repay, Loan, Void)
- **Reconciliation** - Reconciliation data retrieval
- **Reports** - Reporting endpoints (Agent balances, Transaction summary)

### Request Types
Each folder contains:
- **Positive examples** - Valid requests that should succeed
- **Negative examples** - Invalid requests that should fail (for testing error handling)

## Pre-request Script
The collection includes a pre-request script that:
- Automatically injects the `Authorization: Bearer {{token}}` header
- Sets a `timestamp` variable for generating unique idempotency keys

## Environment Variables

### Common Variables
- `baseURL` - API base URL (default: `http://localhost:8000/api`)
- `token` - Bearer token for authentication
- `timestamp` - Auto-generated timestamp for idempotency keys

### Role-Specific Variables
- `user_email` - User email for reference
- `user_role` - User role name
- `capabilities` - List of user capabilities
- `branch_id` - Branch ID for multi-branch testing

## Testing Different Scenarios

### 1. Authorization Testing
- Switch between environments to test different capability levels
- Use Manager environment to test read-only access
- Use Distributor environment to test transaction posting

### 2. Error Handling
- Run negative examples to verify proper error responses
- Test validation errors, authorization failures, and not found scenarios

### 3. Idempotency
- The collection uses dynamic timestamps for idempotency keys
- Test duplicate transaction prevention by running the same request twice

## API Endpoints Covered

### Agent Management
- `GET /telebirr/agents` - List all agents
- `GET /telebirr/agents/{id}` - Get specific agent
- `POST /telebirr/agents` - Create new agent
- `PATCH /telebirr/agents/{id}` - Update agent

### Transaction Operations
- `GET /telebirr/transactions` - List transactions
- `GET /telebirr/transactions/{id}` - Get specific transaction
- `POST /telebirr/transactions/topup` - Post topup transaction
- `POST /telebirr/transactions/issue` - Post issue transaction
- `POST /telebirr/transactions/repay` - Post repayment transaction
- `POST /telebirr/transactions/loan` - Post loan transaction
- `PATCH /telebirr/transactions/{id}/void` - Void transaction

### Reconciliation & Reports
- `GET /telebirr/reconciliation` - Get reconciliation data
- `GET /telebirr/reports/agent-balances` - Agent balances report
- `GET /telebirr/reports/transaction-summary` - Transaction summary report

## Notes

- All POST/PATCH requests include proper JSON payloads
- Idempotency keys are automatically generated using timestamps
- Error responses follow Laravel's validation error format
- Authentication uses Laravel Sanctum tokens
- Branch tenancy is supported via `X-Branch-Id` header (configurable in environments)

## Troubleshooting

1. **401 Unauthorized**: Check that the token is valid and not expired
2. **403 Forbidden**: Verify user has required capabilities for the endpoint
3. **422 Validation Error**: Check request payload matches API requirements
4. **404 Not Found**: Ensure the resource ID exists
5. **500 Server Error**: Check Laravel logs for server-side issues