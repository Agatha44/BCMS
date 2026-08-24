# Testing the Post Bill API

## Prerequisites

1. Make sure you have a valid authentication token
2. Ensure you have test data in the database:
   - Account with account_no
   - Vehicle with matching vehicle_id and plate_no
   - Price list entries for the vehicle's body type

## Test Request

### Using cURL

```bash
curl -X POST http://your-domain.com/api/post-bill \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "bill_desc": "Daily Bundle Subscription",
    "phone": "255123456789",
    "account_no": "ACC001",
    "vehicle_id": 1,
    "plate_no": "T123ABC",
    "bundle_id": 1
  }'
```

### Using Postman

1. Set method to `POST`
2. Set URL to `http://your-domain.com/api/post-bill`
3. Add header: `Authorization: Bearer YOUR_TOKEN_HERE`
4. Add header: `Content-Type: application/json`
5. Set body to raw JSON:

```json
{
    "bill_desc": "Daily Bundle Subscription",
    "phone": "255123456789",
    "account_no": "ACC001",
    "vehicle_id": 1,
    "plate_no": "T123ABC",
    "bundle_id": 1
}
```

## Expected Responses

### Success Response
```json
{
    "success": true,
    "message": "Control Number Request Successfully Sent",
    "data": {
        "bill_id": 123,
        "bill_amount": 5000,
        "control_number": "TBS123456",
        "gepg_response": {
            // GePG response data
        }
    }
}
```

### Validation Error Response
```json
{
    "success": false,
    "message": "Validation Error: The bill_amount field is required."
}
```

### Business Logic Error Response
```json
{
    "success": false,
    "message": "Vehicle not found or plate number does not match"
}
```

## Test Cases

### 1. Valid Request
- All fields provided correctly
- Vehicle exists and plate number matches
- Price list has valid amounts for the vehicle's body type and bundle type
- No outstanding bills

### 2. Missing Required Fields
- Omit one or more required fields
- Should return validation error

### 3. Invalid Vehicle/Plate Combination
- Use vehicle_id that doesn't exist
- Use plate_no that doesn't match vehicle_id
- Should return "Vehicle not found or plate number does not match"

### 4. Invalid Price List Configuration
- Try to create bill for body type that doesn't have price list entries
- Should return "Invalid bundle amount. Please check price list configuration."

### 5. Outstanding Bill
- Try to create bill for plate number that has outstanding bill
- Should return "You have an outstanding bill with Control Number X"

### 6. Invalid Bundle Type
- Use bundle_id other than 1, 2, or 3
- Should return validation error

## Database Verification

After successful API call, check these tables:

1. `bridge_bills` - Should have new record with:
   - bill_amount calculated from price list
   - bill_status = '0' (pending)
   - dist_param = plate_no
   - bundle_id = requested bundle_id

2. Check if GePG integration worked by looking for:
   - contr_num field (control number)
   - Any error codes in error_code field

## Troubleshooting

1. **Authentication Error**: Make sure you're using a valid Bearer token
2. **Validation Errors**: Check that all required fields are provided and valid
3. **Database Errors**: Ensure test data exists in account, vehicle, and price_list tables
4. **GePG Errors**: Check GePG configuration in config/app.php
5. **Transaction Errors**: Check database connection and table structure 