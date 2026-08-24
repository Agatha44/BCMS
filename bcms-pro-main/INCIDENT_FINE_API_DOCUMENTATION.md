# Incident Fine API Documentation

This document describes the Incident Fine management API endpoints implemented in the BCMS Pro Laravel backend.

## Base URL
```
/api/incident-fine
```

## Authentication
All endpoints require authentication using Laravel Sanctum. Include the bearer token in the Authorization header:
```
Authorization: Bearer {your-token}
```

---

## Endpoints

### 1. Get All Incident Fines
Retrieve a list of all incident fines.

**Endpoint:** `GET /api/incident-fine/query`

**Response:**
```json
{
  "success": true,
  "status_code": 1,
  "data": [
    {
      "id": 1,
      "driver_name": "John Doe",
      "plate_number": "T123ABC",
      "vehicle_owner": "Jane Doe",
      "incident_date": "2024-01-15",
      "nature_incident": "Vehicle Payment Evasion",
      "amount": 50000,
      "phone_number": "255712345678",
      "police_rb": "RB12345",
      "payment_type": 1,
      "payer_name": "John Doe",
      "email": "john@example.com",
      "control_num": "99110012345678",
      "psp_receipt_num": null,
      "pay_ref_id": null,
      "t_status": "SP",
      "is_cancelled": 0,
      "created_at": "2024-01-15T10:30:00.000000Z",
      "updated_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "message": "Incident fines retrieved successfully"
}
```

---

### 2. Get Incident Fine Details
Retrieve details of a specific incident fine by ID.

**Endpoint:** `GET /api/incident-fine/query-details/{id}`

**Parameters:**
- `id` (path parameter): The incident fine ID

**Response:**
```json
{
  "success": true,
  "status_code": 1,
  "data": [{
    "id": 1,
    "driver_name": "John Doe",
    ...
  }],
  "message": "Incident fine details retrieved successfully"
}
```

---

### 3. Create New Incident Fine
Create a new incident fine and post it to GePG for payment.

**Endpoint:** `POST /api/incident-fine/create`

**Request Body:**
```json
{
  "driver_name": "John Doe",
  "plate_number": "T123ABC",
  "vehicle_owner": "Jane Doe",
  "incident_date": "2024-01-15",
  "incident_nature": 1,
  "amount": 50000,
  "phone_number": "255712345678",
  "police_rb": "RB12345",
  "payment_type": 1,
  "payer_name": "John Doe",
  "email": "john@example.com"
}
```

**Field Descriptions:**
- `driver_name`: Driver's full name (required, max 50 chars)
- `plate_number`: Vehicle plate number (required, max 50 chars)
- `vehicle_owner`: Owner's name (required, max 50 chars)
- `incident_date`: Date of incident (required, format: YYYY-MM-DD)
- `incident_nature`: Type of incident (required, values: 1 = Vehicle Payment Evasion, 2 = Motorcycle Evasion, 3 = Infrastructure Damage)
- `amount`: Fine amount in TZS (required, min: 1)
- `phone_number`: Contact phone number (required, max 50 chars)
- `police_rb`: Police RB number (required, max 50 chars)
- `payment_type`: Who pays (required, values: 1 = Driver, 2 = Insurance, 3 = Owner)
- `payer_name`: Name of person paying (required, max 50 chars)
- `email`: Payer's email (required, valid email format)

**Success Response:**
```json
{
  "success": true,
  "status_code": 1,
  "data": {
    "id": 1,
    "control_num": "99110012345678",
    "t_status": "SP",
    ...
  },
  "message": "Incident fine created successfully"
}
```

**Error Response (GePG Failed):**
```json
{
  "success": false,
  "status_code": 0,
  "message": "Bill created but failed to post to GePG",
  "data": {
    "status": "invalid_request",
    "error_code": "7242"
  }
}
```

---

### 4. Update Incident Fine
Update an existing incident fine (only allowed for unpaid, uncancelled bills).

**Endpoint:** `POST /api/incident-fine/edit-incident`

**Request Body:**
```json
{
  "id": 1,
  "driver_name": "John Doe Updated",
  "plate_number": "T123ABC",
  "vehicle_owner": "Jane Doe",
  "incident_date": "2024-01-15",
  "incident_nature": 1,
  "amount": 50000,
  "phone_number": "255712345678",
  "police_rb": "RB12345",
  "payment_type": 1,
  "payer_name": "John Doe",
  "email": "john@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "status_code": 1,
  "data": { ... },
  "message": "Incident fine updated successfully"
}
```

**Error Responses:**
- Cannot edit paid bills: `status_code: 400, message: "Cannot edit paid incident fine"`
- Cannot edit cancelled bills: `status_code: 400, message: "Cannot edit cancelled incident fine"`

---

### 5. Cancel Bill
Cancel an unpaid incident fine bill.

**Endpoint:** `POST /api/incident-fine/bill-cancellation`

**Request Body:**
```json
{
  "id": 1,
  "cancel_reason": "Reason for cancellation"
}
```

**Field Descriptions:**
- `id`: Incident fine ID (required, integer)
- `cancel_reason`: Reason for cancellation (required, max 200 chars)

**Response:**
```json
{
  "success": true,
  "status_code": 1,
  "data": {
    "id": 1,
    "is_cancelled": 1,
    "cancel_reason": "Reason for cancellation",
    "bill_cancel_date": "2024-01-15T14:30:00.000000Z",
    ...
  },
  "message": "Bill cancelled successfully"
}
```

**Error Responses:**
- Cannot cancel paid bills: `status_code: 400, message: "Cannot cancel paid bill"`
- Already cancelled: `status_code: 400, message: "Bill already cancelled"`

---

### 6. View Error Reason
Get error details for failed incident fine bills.

**Endpoint:** `GET /api/incident-fine/view-reason/{id}`

**Parameters:**
- `id` (path parameter): The incident fine ID

**Response:**
```json
{
  "success": true,
  "status_code": 1,
  "data": [
    {
      "error_code": "7242",
      "description": "Invalid request data"
    },
    {
      "error_code": "7267",
      "description": "Invalid email address"
    }
  ],
  "message": "Error details retrieved successfully"
}
```

---

### 7. Repost Bill
Repost a failed incident fine bill to GePG.

**Endpoint:** `POST /api/incident-fine/repost-bill`

**Request Body:**
```json
{
  "id": 1
}
```

**Response:**
```json
{
  "success": true,
  "status_code": 1,
  "data": {
    "id": 1,
    "control_num": "99110012345678",
    "t_status": "SP",
    "error_code": null,
    ...
  },
  "message": "Bill reposted successfully"
}
```

**Error Responses:**
- Cannot repost paid bills: `status_code: 400, message: "Cannot repost paid bill"`
- Cannot repost cancelled bills: `status_code: 400, message: "Cannot repost cancelled bill"`

---

## Status Codes

### Transaction Status (t_status)
- `SP`: Successful (bill posted to GePG successfully)
- `GF`: Failed (bill posting to GePG failed)

### Bill Status
- `is_cancelled`: `0` = Active, `1` = Cancelled
- `psp_receipt_num`: `null` = Unpaid, `{value}` = Paid

### Incident Nature
- `1`: Vehicle Payment Evasion
- `2`: Motorcycle Evasion
- `3`: Infrastructure Damage

### Payment Type
- `1`: Driver
- `2`: Insurance
- `3`: Owner

---

## Error Handling

All endpoints return consistent error responses:

```json
{
  "success": false,
  "status_code": 0,
  "message": "Error message description",
  "data": {}
}
```

### Common HTTP Status Codes
- `200`: Success
- `400`: Bad Request (business logic error)
- `404`: Not Found
- `422`: Validation Error
- `500`: Internal Server Error

### Validation Errors
Validation errors return a `422` status with detailed field errors:

```json
{
  "success": false,
  "status_code": 0,
  "message": "Validation Error",
  "data": {
    "driver_name": ["The driver name field is required."],
    "email": ["The email must be a valid email address."]
  }
}
```

---

## Integration with GePG

When creating or reposting an incident fine, the system automatically:

1. Generates a unique bill ID with prefix "INF" (e.g., `INF123`)
2. Posts the bill to GePG payment gateway
3. Receives a control number for payment
4. Updates the incident fine record with:
   - `control_num`: GePG control number
   - `t_status`: Transaction status
   - `error_code`: Error codes if failed

### Bill Expiry
- Bills expire **30 days** after creation
- Expired bills can be reposted to generate new control numbers

---

## Notes

- All endpoints require authentication via Sanctum
- All timestamps are in UTC and use ISO 8601 format
- Amount is stored in TZS (Tanzanian Shillings)
- Phone numbers should include country code (e.g., 255712345678)
- The system logs all operations for audit purposes

---

## Testing

### Postman Collection
Import the BCMS Pro Postman collection and use the following sequence:

1. **Login** to get authentication token
2. **Create Incident Fine** - POST /api/incident-fine/create
3. **Get All Incident Fines** - GET /api/incident-fine/query
4. **Get Details** - GET /api/incident-fine/query-details/{id}
5. **Update** (if needed) - POST /api/incident-fine/edit-incident
6. **View Errors** (if failed) - GET /api/incident-fine/view-reason/{id}
7. **Repost** (if failed) - POST /api/incident-fine/repost-bill
8. **Cancel** (if needed) - POST /api/incident-fine/bill-cancellation

---

## Database Schema

The incident fine data is stored in the `incident_fine` table with the following key fields:

- Primary key: `id`
- Business logic: `nature_incident`, `amount`, `payment_type`, `is_cancelled`
- Driver/Vehicle: `driver_name`, `plate_number`, `vehicle_owner`, `police_rb`
- Payer: `payer_name`, `phone_number`, `email`
- GePG Integration: `control_num`, `psp_receipt_num`, `pay_ref_id`, `t_status`, `error_code`
- Audit: `created_by`, `updated_by`, `bill_cancel_by`, `created_at`, `updated_at`

