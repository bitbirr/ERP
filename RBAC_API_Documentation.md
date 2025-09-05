# RBAC API Documentation

## Overview
This document provides comprehensive documentation for the Role-Based Access Control (RBAC) API endpoints in the Najib Shop - Back Office. The API implements a flexible RBAC system with users, roles, capabilities, and user-role assignments.

## Authentication
All API endpoints require authentication using Sanctum tokens. Include the token in the Authorization header:
```
Authorization: Bearer {token}
```

## API Base URL
```
http://localhost:8000/api
```

## Collections Overview

### 1. RBAC.postman_collection.json
Contains core RBAC management endpoints for roles, capabilities, and system administration.

### 2. User-Management.postman_collection.json
User CRUD operations for managing system users.

### 3. Permissions-Management.postman_collection.json
Permission/capability management through roles (current implementation).

### 4. Roles-Management.postman_collection.json
Role CRUD operations and capability synchronization.

### 5. User-Role-Assignment.postman_collection.json
User-role assignment and permission retrieval.

### 6. Capability.postman_collection.json
Capability management (with notes for future direct CRUD implementation).

## Detailed Endpoint Documentation

### User Management Endpoints

#### GET /users
**Description:** Retrieve paginated list of all users with their role assignments.

**Authentication:** Required (users.manage capability)

**Query Parameters:**
- `per_page` (optional): Number of users per page (default: 15)

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "string",
      "email": "string",
      "created_at": "datetime",
      "role_assignments": [
        {
          "id": "uuid",
          "role": {
            "id": "uuid",
            "name": "string",
            "slug": "string"
          }
        }
      ]
    }
  ],
  "links": {...},
  "meta": {...}
}
```

#### POST /users
**Description:** Create a new user.

**Authentication:** Required (users.manage capability)

**Request Body:**
```json
{
  "name": "string (required)",
  "email": "string (required, unique)",
  "password": "string (required)",
  "password_confirmation": "string (required)",
  "email_verified_at": "datetime (optional)"
}
```

#### GET /users/{user}
**Description:** Retrieve a specific user with role assignments.

**Authentication:** Required (users.manage capability)

**Parameters:**
- `user`: User UUID

#### PATCH /users/{user}
**Description:** Update an existing user.

**Authentication:** Required (users.manage capability)

**Request Body:**
```json
{
  "name": "string (optional)",
  "email": "string (optional, unique)",
  "password": "string (optional)"
}
```

#### DELETE /users/{user}
**Description:** Delete a user.

**Authentication:** Required (users.manage capability)

**Parameters:**
- `user`: User UUID

### Role Management Endpoints

#### GET /rbac/roles
**Description:** Retrieve all roles with their associated capabilities.

**Authentication:** Required (roles.view capability)

**Response:**
```json
[
  {
    "id": "uuid",
    "name": "string",
    "slug": "string",
    "is_system": "boolean",
    "capabilities": ["capability_key1", "capability_key2"]
  }
]
```

#### POST /rbac/roles
**Description:** Create a new role.

**Authentication:** Required (roles.manage capability)

**Request Body:**
```json
{
  "name": "string (required)",
  "slug": "string (required, unique)",
  "is_system": "boolean (optional, default: false)"
}
```

#### PATCH /rbac/roles/{id}
**Description:** Update an existing role.

**Authentication:** Required (roles.manage capability)

**Parameters:**
- `id`: Role UUID

**Request Body:**
```json
{
  "name": "string (optional)",
  "slug": "string (optional, unique)",
  "is_system": "boolean (optional)"
}
```

#### POST /rbac/roles/{id}/capabilities
**Description:** Sync capabilities for a specific role.

**Authentication:** Required (roles.manage capability)

**Parameters:**
- `id`: Role UUID

**Request Body:**
```json
{
  "capability_keys": ["array of capability keys"]
}
```

### User-Role Assignment Endpoints

#### POST /rbac/users/{user}/roles
**Description:** Assign a role to a user.

**Authentication:** Required (users.manage capability)

**Parameters:**
- `user`: User UUID

**Request Body:**
```json
{
  "role_slug": "string (required)",
  "branch_code": "string (optional)"
}
```

#### GET /rbac/users/{userId}/permissions
**Description:** Get effective permissions for a user.

**Authentication:** Required

**Parameters:**
- `userId`: User UUID

**Headers:**
- `X-Branch-Id`: Branch UUID (optional, for branch-specific permissions)

**Response:**
```json
{
  "user_id": "uuid",
  "capabilities": ["capability_key1", "capability_key2"]
}
```

### RBAC System Endpoints

#### POST /rbac/rebuild
**Description:** Rebuild RBAC cache for all users or a specific user.

**Authentication:** Required (system.admin capability)

**Request Body:**
```json
{
  "user_id": "uuid (optional)"
}
```

## Capability System

### Current Implementation
Capabilities are managed through roles using the `/rbac/roles/{id}/capabilities` endpoint. Direct capability CRUD operations are not currently implemented but can be added in the future.

### Common Capability Keys
- `users.manage` - Full user management
- `users.view` - View users
- `roles.manage` - Full role management
- `roles.view` - View roles
- `system.admin` - System administration
- `inventory.read` - Read inventory
- `inventory.manage` - Manage inventory
- `products.read` - Read products
- `products.manage` - Manage products

## Environments

### Available Environments
1. **RBAC_Admin_Environment.postman_environment.json** - Admin access with full permissions
2. **RBAC_Manager_Environment.postman_environment.json** - Manager access with elevated permissions
3. **RBAC_User_Environment.postman_environment.json** - Regular user access with basic permissions

### Environment Variables
- `baseURL`: API base URL
- `token`: Authentication token
- `user_id`: Test user ID
- `role_id`: Test role ID
- `branch_id`: Test branch ID

## Testing with Newman

### Prerequisites
1. Install Newman: `npm install -g newman`
2. Import collections and environments into Postman
3. Export collections and environments as JSON files

### Running Tests
```bash
# Test with admin environment
newman run RBAC.postman_collection.json -e RBAC_Admin_Environment.postman_environment.json

# Test with manager environment
newman run RBAC.postman_collection.json -e RBAC_Manager_Environment.postman_environment.json

# Test all collections
newman run User-Management.postman_collection.json -e RBAC_Admin_Environment.postman_environment.json
newman run Roles-Management.postman_collection.json -e RBAC_Admin_Environment.postman_environment.json
newman run User-Role-Assignment.postman_collection.json -e RBAC_Admin_Environment.postman_environment.json
```

### Test Results
Newman will generate detailed test reports showing:
- Passed/Failed requests
- Response times
- Error details
- Test coverage

## Error Handling

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `204` - No Content
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

### Error Response Format
```json
{
  "message": "Error description",
  "errors": {
    "field": ["Error details"]
  }
}
```

## Security Considerations

1. **Token Management**: Tokens should be rotated regularly
2. **Role Assignment**: Only authorized users can assign roles
3. **Capability Validation**: All endpoints validate user capabilities
4. **Branch Isolation**: Branch-specific permissions are enforced
5. **Audit Logging**: All RBAC operations are logged

## Future Enhancements

1. **Direct Capability CRUD**: Implement direct capability management endpoints
2. **Role Hierarchies**: Support for role inheritance
3. **Permission Groups**: Organize capabilities into groups
4. **Dynamic Permissions**: Runtime permission evaluation
5. **Policy Engine**: Advanced policy-based access control

## Support

For API support or questions, refer to the system documentation or contact the development team.