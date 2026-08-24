# Maker-Checker Employee Approval System

## Overview

This document describes the maker-checker (four-eyes) approval mechanism implemented for employee management operations. The system ensures that critical employee operations (creation, termination, and deletion) require approval from authorized users before being executed.

## Architecture

### Components

1. **EmployeeStatus Constants** (`app/Constants/EmployeeStatus.php`)
   - Defines all employee status values
   - Provides helper methods for status management

2. **BridgeEmployeeStatus Model** (`app/Models/Bms/BridgeEmployeeStatus.php`)
   - Manages employee status history
   - Tracks approval workflow

3. **EmployeeApprovalService** (`app/Services/EmployeeApprovalService.php`)
   - Core business logic for approval workflow
   - Handles submission, approval, and rejection

4. **EmployeeApprovalController** (`app/Http/Controllers/Bms/EmployeeApprovalController.php`)
   - API endpoints for approval operations

5. **BridgeEmployeeController** (Updated)
   - Modified to use maker-checker workflow

## Employee Status Flow

### Status Values

- **Pending Statuses:**
  - `pending_creation` - Employee creation awaiting approval
  - `pending_termination` - Employee termination awaiting approval
  - `pending_deletion` - Employee deletion awaiting approval

- **Approved/Active Statuses:**
  - `approved` - Employee creation approved
  - `active` - Employee is active

- **Terminated/Deleted Statuses:**
  - `terminated` - Employee termination approved
  - `deleted` - Employee deletion approved

- **Rejected Status:**
  - `rejected` - Request was rejected

## API Endpoints

### Employee Operations (Maker)

#### 1. Create Employee (Requires Approval)
```
POST /api/bridge-employees/register
```

**Request Body:**
```json
{
  "fname": "John",
  "sname": "Doe",
  "national_id": "1234567890123456",
  "email": "john.doe@example.com",
  "mobile": "255712345678",
  "approval_description": "New employee registration"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "employee": {...},
    "national_id": "1234567890123456",
    "approval_status": {
      "status_id": 1,
      "status": "pending_creation",
      "message": "Awaiting approval"
    }
  },
  "message": "Bridge employee registered successfully and submitted for approval"
}
```

#### 2. Submit Termination Request
```
POST /api/bridge-employees/{nationalId}/submit-termination
```

**Request Body:**
```json
{
  "reason_id": 1,
  "description": "Employee requested termination"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status_id": 2,
    "status": "pending_termination",
    "message": "Termination request submitted for approval"
  },
  "message": "Termination request submitted successfully"
}
```

#### 3. Submit Deletion Request
```
POST /api/bridge-employees/{nationalId}/submit-deletion
```

**Request Body:**
```json
{
  "reason_id": 2,
  "description": "Duplicate record"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status_id": 3,
    "status": "pending_deletion",
    "message": "Deletion request submitted for approval"
  },
  "message": "Deletion request submitted successfully"
}
```

### Approval Operations (Checker)

#### 1. Get Pending Approvals
```
GET /api/employee-approvals/pending?type=pending_creation
```

**Query Parameters:**
- `type` (optional): Filter by status type (`pending_creation`, `pending_termination`, `pending_deletion`)

**Response:**
```json
{
  "success": true,
  "data": {
    "pending_approvals": [
      {
        "id": 1,
        "national_id": "1234567890123456",
        "employee_status": "pending_creation",
        "description": "Employee creation submitted for approval",
        "cdate": "2026-01-15T10:00:00.000000Z",
        "employee": {...}
      }
    ],
    "count": 1
  },
  "message": "Pending approvals retrieved successfully"
}
```

#### 2. Approve Employee Creation
```
POST /api/employee-approvals/{statusId}/approve-creation
```

**Request Body:**
```json
{
  "comments": "Approved after verification"
}
```

**Response:**
```json
{
  "success": true,
  "data": null,
  "message": "Employee creation approved successfully"
}
```

#### 3. Approve Employee Termination
```
POST /api/employee-approvals/{statusId}/approve-termination
```

**Request Body:**
```json
{
  "comments": "Termination approved"
}
```

**Response:**
```json
{
  "success": true,
  "data": null,
  "message": "Employee termination approved successfully"
}
```

#### 4. Approve Employee Deletion
```
POST /api/employee-approvals/{statusId}/approve-deletion
```

**Request Body:**
```json
{
  "comments": "Deletion approved"
}
```

**Response:**
```json
{
  "success": true,
  "data": null,
  "message": "Employee deletion approved successfully"
}
```

#### 5. Reject Request
```
POST /api/employee-approvals/{statusId}/reject
```

**Request Body:**
```json
{
  "rejection_reason": "Incomplete information provided"
}
```

**Response:**
```json
{
  "success": true,
  "data": null,
  "message": "Request rejected successfully"
}
```

## Workflow

### Employee Creation Workflow

1. **Maker** creates employee via `POST /api/bridge-employees/register`
   - Employee is created with status `pending_creation`
   - Status record is created in `bridge_employee_status` table
   - Employee cannot be used until approved

2. **Checker** reviews pending approvals via `GET /api/employee-approvals/pending`

3. **Checker** approves or rejects:
   - **Approve**: `POST /api/employee-approvals/{statusId}/approve-creation`
     - Employee status changes to `approved`
     - Account status becomes `Active`
   - **Reject**: `POST /api/employee-approvals/{statusId}/reject`
     - Employee status changes to `rejected`
     - Previous status is restored if applicable

### Employee Termination Workflow

1. **Maker** submits termination request via `POST /api/bridge-employees/{nationalId}/submit-termination`
   - Employee status changes to `pending_termination`
   - Status record is created

2. **Checker** reviews and approves/rejects:
   - **Approve**: Employee status changes to `terminated`, account becomes `Inactive`
   - **Reject**: Previous status is restored

### Employee Deletion Workflow

1. **Maker** submits deletion request via `POST /api/bridge-employees/{nationalId}/submit-deletion`
   - Employee status changes to `pending_deletion`
   - Status record is created

2. **Checker** reviews and approves/rejects:
   - **Approve**: Employee record is permanently deleted
   - **Reject**: Previous status is restored

## Database Schema

### bridge_employee_status Table

The `bridge_employee_status` table tracks all status changes and approvals:

- `id` - Primary key
- `national_id` - Reference to employee
- `employee_status` - Current status (pending_creation, pending_termination, etc.)
- `previous_employee_status` - Previous status before change
- `reason_id` - Optional reason for status change
- `description` - Description/comments
- `cby` - Created by (maker)
- `cdate` - Created date
- `statusdate` - Status change date
- `verified_by` - User who verified
- `verify_date` - Verification date
- `approved_by` - User who approved
- `approve_date` - Approval date
- `verified` - Boolean flag
- `approved` - Boolean flag

## Usage Examples

### Example 1: Create and Approve Employee

```php
// Step 1: Maker creates employee
$response = Http::post('/api/bridge-employees/register', [
    'fname' => 'John',
    'sname' => 'Doe',
    'national_id' => '1234567890123456',
    'email' => 'john.doe@example.com',
    'mobile' => '255712345678'
]);

$statusId = $response->json()['data']['approval_status']['status_id'];

// Step 2: Checker approves
Http::post("/api/employee-approvals/{$statusId}/approve-creation", [
    'comments' => 'Approved after verification'
]);
```

### Example 2: Terminate Employee

```php
// Step 1: Maker submits termination
$response = Http::post('/api/bridge-employees/1234567890123456/submit-termination', [
    'reason_id' => 1,
    'description' => 'Employee requested termination'
]);

$statusId = $response->json()['data']['status_id'];

// Step 2: Checker approves
Http::post("/api/employee-approvals/{$statusId}/approve-termination", [
    'comments' => 'Termination approved'
]);
```

## Security Considerations

1. **Authorization**: Ensure proper middleware/guards are in place to restrict:
   - Maker endpoints to authorized users
   - Checker/approval endpoints to approvers only

2. **Audit Trail**: All operations are logged in:
   - `bridge_employee_status` table (status history)
   - Application logs (via Log facade)

3. **Status Validation**: The service validates:
   - Status transitions are valid
   - Requests aren't already approved/rejected
   - Employee exists before operations

## Best Practices

1. **Role-Based Access**: Implement role-based access control:
   - Makers: Can create/submit requests
   - Checkers: Can approve/reject requests

2. **Notifications**: Consider implementing notifications for:
   - Makers when requests are approved/rejected
   - Checkers when new requests are submitted

3. **Bulk Operations**: For bulk approvals, consider adding bulk endpoints

4. **Status Queries**: Use scopes for common queries:
   ```php
   BridgeEmployeeStatus::pendingApproval()->get();
   BridgeEmployeeStatus::approved()->get();
   ```

## Migration Notes

The existing `bridge_employee_status` table already has the necessary fields for the maker-checker workflow:
- `approved`, `approved_by`, `approve_date`
- `verified`, `verified_by`, `verify_date`
- `previous_employee_status`

No additional migration is required.

## Testing

Test scenarios to cover:

1. ✅ Employee creation with approval workflow
2. ✅ Employee termination with approval workflow
3. ✅ Employee deletion with approval workflow
4. ✅ Rejection of pending requests
5. ✅ Status restoration on rejection
6. ✅ Duplicate request prevention
7. ✅ Invalid status transition handling

