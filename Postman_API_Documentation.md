# ERP API Postman Collections

This repository contains Postman collections and environments for testing the ERP system's Products, Inventory, and Stock Movement APIs.

## Files Created

1. **Products.postman_collection.json** - Complete CRUD operations for products
2. **Inventory.postman_collection.json** - Inventory management operations
3. **StockMovement.postman_collection.json** - Stock movement history and reporting
4. **ERP_API_Environments.postman_environment.json** - Environment variables for different roles and environments

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

## Usage Tips

1. **Switch Roles**: Change the `token` variable to test different user roles
2. **Environment Switching**: Use different baseURL variables for local/staging/production
3. **Test Data**: Update sample IDs with actual data from your database
4. **Sequential Testing**: Run inventory operations in sequence to maintain data consistency
5. **RBAC Testing**: Test with different user roles to verify capability restrictions

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