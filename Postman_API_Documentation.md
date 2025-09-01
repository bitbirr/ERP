# ERP API Postman Collections

This repository contains Postman collections and environments for testing the ERP system's Products, Inventory, and Stock Movement APIs.

## Files Created

1. **Products.postman_collection.json** - Complete CRUD operations for products
2. **Inventory.postman_collection.json** - Inventory management operations
3. **StockMovement.postman_collection.json** - Stock movement history and reporting
4. **ERP_API_Environments.postman_environment.json** - Environment variables for different roles and environments
5. **Vouchers API** - Digital voucher lifecycle management (implemented in this task)

## Setup Instructions

### 1. Import Collections and Environment

1. Open Postman
2. Click "Import" button
3. Import all four JSON files:
   - Products.postman_collection.json
   - Inventory.postman_collection.json
   - StockMovement.postman_collection.json
   - ERP_API_Environments.postman_environment.json

### 2. Configure Environment

1. Select "ERP API Environments" from the environment dropdown
2. Update the `baseURL` variable to match your Laravel application:
   - Local: `http://localhost:8000/api`
   - Staging: `https://your-staging-domain.com/api`
   - Production: `https://your-production-domain.com/api`

3. Update the token variables with actual JWT tokens from your Laravel application:
   - `admin_token` - For admin operations (full access)
   - `manager_token` - For management operations
   - `inventory_token` - For inventory operations
   - `sales_token` - For sales operations
   - `audit_token` - For read-only access

### 3. Get Authentication Tokens

Use the existing `/api/sanctum/token` endpoint to obtain tokens:

```bash
POST http://localhost:8000/api/sanctum/token
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "secret123",
  "device_name": "Postman"
}
```

**Example working token:** `24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15`

Replace the placeholder tokens in the environment with actual tokens.

### 3. Get Authentication Tokens

Use the existing `/api/sanctum/token` endpoint to obtain tokens:

```bash
POST http://localhost:8000/api/sanctum/token
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password",
  "device_name": "Postman"
}
```

Replace the placeholder tokens in the environment with actual tokens.

### 4. Configure Base URLs

Update the `baseURL` variable based on your environment:
- Local: `http://localhost:8000/api`
- Staging: `https://staging-api.erp.example.com/api`
- Production: `https://api.erp.example.com/api`

## API Endpoints Covered

### Products API
- `GET /products` - List all products
- `POST /products` - Create new product
- `GET /products/{id}` - Get specific product
- `PATCH /products/{id}` - Update product
- `DELETE /products/{id}` - Delete product

### Inventory API
- `GET /inventory` - List inventory items
- `GET /inventory/{product}/{branch}` - Get inventory for specific product/branch
- `POST /inventory/opening` - Set opening balance
- `POST /inventory/receive` - Receive stock
- `POST /inventory/reserve` - Reserve stock
- `POST /inventory/unreserve` - Unreserve stock
- `POST /inventory/issue` - Issue stock
- `POST /inventory/transfer` - Transfer stock between branches
- `POST /inventory/adjust` - Adjust stock levels

### Stock Movement API
- `GET /stock-movements` - List stock movements with optional filters
- `GET /stock-movements/{id}` - Get specific stock movement
- `GET /stock-movements/reports/summary` - Movement summary report
- `GET /stock-movements/reports/by-product/{id}` - Movements by product
- `GET /stock-movements/reports/by-branch/{id}` - Movements by branch

### Voucher API
- `POST /vouchers/batches` - Receive voucher batch
- `GET /vouchers/batches` - List voucher batches
- `GET /vouchers/batches/{batchNumber}` - Get specific voucher batch
- `GET /vouchers/batches/{batchNumber}/available` - Get available vouchers in batch
- `POST /vouchers/reserve` - Reserve vouchers for order
- `DELETE /vouchers/reservations/{reservationId}` - Cancel voucher reservation
- `GET /vouchers/orders/{orderId}/reservations` - Get reservations for order
- `PATCH /vouchers/reservations/{reservationId}/extend` - Extend reservation expiry
- `POST /vouchers/reservations/cleanup` - Cleanup expired reservations
- `POST /vouchers/issue` - Issue vouchers for fulfillment
- `POST /vouchers/issue-by-reservations` - Issue vouchers by reservation IDs
- `GET /vouchers/orders/{orderId}/issuances` - Get issuances for order
- `GET /vouchers/fulfillments/{fulfillmentId}/issuances` - Get issuances for fulfillment
- `PATCH /vouchers/issuances/{issuanceId}/void` - Void voucher issuance

## Authentication

All requests automatically include the `Authorization: Bearer {{token}}` header via pre-request scripts.

## Test Examples

Each collection includes:
- **Positive test cases** - Expected successful scenarios
- **Negative test cases** - Error handling scenarios like:
  - Unauthorized access
  - Not found resources
  - Validation errors
  - Insufficient stock
  - Invalid data

## Environment Variables

### Role-based Tokens
- `admin_token` - Full system access
- `manager_token` - Management operations
- `inventory_token` - Inventory operations
- `sales_token` - Sales operations
- `audit_token` - Read-only access

### Test Data Variables
- `product_id` - Sample product UUID
- `branch_id` - Sample branch UUID
- `to_branch_id` - Destination branch for transfers
- `stock_movement_id` - Sample stock movement UUID
- `receipt_id` - Sample receipt UUID
- `voucher_batch_number` - Sample voucher batch number
- `order_id` - Sample order ID
- `fulfillment_id` - Sample fulfillment ID

## Working API Examples

### Products API
```bash
# List Products
GET http://localhost:8000/api/products
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15

# Response: Returns paginated list of 145 products including Yimulu, Voucher cards, EVD, etc.
```

### Inventory API
```bash
# List Inventory Items
GET http://localhost:8000/api/inventory
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15

# Response: Returns inventory data showing 67 units on hand, 67 reserved for Yimulu product
```

### Stock Movements API
```bash
# List Stock Movements
GET http://localhost:8000/api/stock-movements
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15

# Response: Returns 34 stock movement records including OPENING, RESERVE, ISSUE operations
```

### Voucher API

#### 1. Receive Voucher Batch
```bash
POST http://localhost:8000/api/vouchers/batches
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15
Content-Type: application/json

{
  "batch_number": "VOUCHER_BATCH_001",
  "serial_start": "000100",
  "serial_end": "000105",
  "total_vouchers": 6,
  "metadata": {
    "supplier": "Test Supplier",
    "batch_date": "2025-01-01",
    "voucher_type": "gift_card"
  }
}

# Response: Creates batch and 6 individual vouchers with serial numbers 000100-000105
```

#### 2. Reserve Vouchers for Order
```bash
POST http://localhost:8000/api/vouchers/reserve
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15
Content-Type: application/json

{
  "order_id": "ORDER_12345",
  "quantity": 3,
  "batch_number": "VOUCHER_BATCH_001"
}

# Response: Reserves 3 vouchers for the order, changes status from 'available' to 'reserved'
```

#### 3. Issue Vouchers on Fulfillment
```bash
POST http://localhost:8000/api/vouchers/issue
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15
Content-Type: application/json

{
  "order_id": "ORDER_12345",
  "fulfillment_id": "FULFILLMENT_001",
  "voucher_ids": [1, 2, 3]
}

# Response: Issues reserved vouchers, changes status to 'issued', creates issuance records
```

#### 4. Get Voucher Batch Details
```bash
GET http://localhost:8000/api/vouchers/batches/VOUCHER_BATCH_001
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15

# Response: Returns batch details including serial range metadata and voucher counts
```

#### 5. Get Available Vouchers in Batch
```bash
GET http://localhost:8000/api/vouchers/batches/VOUCHER_BATCH_001/available
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15

# Response: Returns list of available vouchers in the batch
```

#### 6. Get Order Reservations
```bash
GET http://localhost:8000/api/vouchers/orders/ORDER_12345/reservations
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15

# Response: Returns all voucher reservations for the specified order
```

#### 7. Cancel Reservation
```bash
DELETE http://localhost:8000/api/vouchers/reservations/1
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15

# Response: Cancels reservation, makes voucher available again
```

#### 8. Extend Reservation
```bash
PATCH http://localhost:8000/api/vouchers/reservations/1/extend
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15
Content-Type: application/json

{
  "hours": 48
}

# Response: Extends reservation expiry by 48 hours
```

#### 9. Void Issuance
```bash
PATCH http://localhost:8000/api/vouchers/issuances/1/void
Authorization: Bearer 24|hoCUoBbmKN8T4ScjsSM7aMweruenHRwoohap03B7a8709f15
Content-Type: application/json

{
  "reason": "Customer return"
}

# Response: Voids issuance, makes voucher available again
```

## Voucher Lifecycle Business Process

The voucher system implements a complete digital voucher lifecycle with the following states:

### 1. Batch Reception
- Receive voucher batches with serial number ranges
- Automatically generate individual voucher records
- Store serial range metadata for audit and tracking
- Status: `received` → `processed`

### 2. Voucher Reservation
- Reserve specific vouchers for customer orders
- Prevent double-booking of vouchers
- Automatic expiry handling (default 24 hours)
- Status: `available` → `reserved`

### 3. Fulfillment & Issuance
- Issue reserved vouchers upon order fulfillment
- Create issuance records for tracking
- Update voucher status to issued
- Status: `reserved` → `issued`

### 4. Management Operations
- Cancel reservations (returns vouchers to available pool)
- Extend reservation expiry times
- Void issuances for returns/refunds
- Cleanup expired reservations automatically

### Key Features
- **Serial Range Tracking**: Complete serial number ranges stored in metadata
- **Audit Trail**: Full history of voucher state changes
- **Idempotent Operations**: Safe retry of operations
- **RBAC Integration**: Capability-based access control
- **Batch Processing**: Efficient handling of large voucher batches

## Usage Tips

1. **Switch Roles**: Change the `token` variable to test different user roles
2. **Environment Switching**: Use different baseURL variables for local/staging/production
3. **Test Data**: Update sample IDs with actual data from your database
4. **Sequential Testing**: Run inventory operations in sequence to maintain data consistency
5. **RBAC Testing**: Test with different user roles to verify capability restrictions
6. **Voucher Lifecycle**: Test complete voucher workflows from batch reception to issuance

## Security Notes

- Never commit actual JWT tokens to version control
- Use environment-specific tokens
- Rotate tokens regularly
- Test with least-privilege principles

## Troubleshooting

1. **401 Unauthorized**: Check token validity and expiration
2. **403 Forbidden**: Verify user has required capabilities
3. **404 Not Found**: Confirm resource IDs exist
4. **422 Unprocessable Entity**: Check request data validation
5. **500 Internal Server Error**: Check Laravel logs for server-side issues