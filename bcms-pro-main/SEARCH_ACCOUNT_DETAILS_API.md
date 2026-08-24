# Search Account Details API

## Overview

The Search Account Details API allows you to retrieve basic account information using an account number. This API provides essential account details including account information, associated vehicles, and active bundle subscriptions.

## Endpoint

```
POST /api/search-account-details
```

## Request Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| `account_no` | string | Yes | The account number to search for | "ACC001" |

### Request Example

```json
{
    "account_no": "ACC001"
}
```

## Response Format

### Success Response

```json
{
    "success": true,
    "status_code": 1,
    "data": {
        "account_no": "ACC001",
        "account_name": "John Doe",
        "account_balance": 50000.00,
        "phone": "255123456789",
        "email": "john.doe@example.com",
        "status": "1",
        "vehicles": [
            {
                "id": 456,
                "plate_no": "ABC123",
                "body_type": "Sedan",
                "status": 1,
                "exempted": 0
            }
        ],
        "active_bundles": [
            {
                "id": 101,
                "plate_no": "ABC123",
                "bundle_description": "Monthly Bundle",
                "start_date": "2024-01-01",
                "expire_date": "2024-02-01",
                "duration": 30
            }
        ]
    },
    "message": "Account details retrieved successfully"
}
```

### Error Response

```json
{
    "success": false,
    "status_code": 0,
    "message": "Account not found",
    "data": {
        "account_no": "INVALID_ACC",
        "message": "No account found with the provided account number"
    }
}
```

## Business Logic

### Account Information Retrieval
1. **Account Lookup**: Searches for account using the provided account number
2. **Basic Information**: Retrieves account number, name, balance, contact info, and status

### Vehicle Information
1. **Associated Vehicles**: Retrieves all vehicles linked to the account
2. **Basic Details**: Includes plate number, body type, status, and exemption information

### Active Bundle Subscriptions
1. **Active Bundles Only**: Retrieves only active and non-expired bundle subscriptions
2. **Bundle Details**: Includes bundle description, start/expire dates, and duration

## Error Handling

### Validation Errors
- **Missing account_no**: Returns validation error
- **Invalid account_no format**: Returns validation error

### Business Logic Errors
- **Account not found**: Returns specific error message with account number
- **Database errors**: Returns generic error with details logged

### Common Error Scenarios

```json
{
    "success": false,
    "status_code": 0,
    "message": "Validation failed",
    "data": {
        "account_no": ["The account no field is required."]
    }
}
```

```json
{
    "success": false,
    "status_code": 0,
    "message": "Error occurred during account search",
    "data": {
        "account_no": "ACC001",
        "error": "Database connection error"
    }
}
```

## Use Cases

### 1. Account Verification
- Verify account existence and status
- Check account balance
- Validate account holder details

### 2. Vehicle Management
- View all vehicles associated with an account
- Check vehicle status and exemption information

### 3. Bundle Management
- Monitor active bundle subscriptions
- Check bundle expiration dates

### 4. Customer Support
- Provide basic account overview
- Quick account lookup

## Security Considerations

### Authentication
- API requires proper authentication
- Access control based on user permissions
- Rate limiting to prevent abuse

### Data Privacy
- Sensitive information is properly handled
- Personal data is protected
- Audit logging for all access

### Input Validation
- Strict validation of account numbers
- SQL injection prevention
- XSS protection

## Performance Considerations

### Database Optimization
- Efficient queries with minimal joins
- Only essential fields selected
- Active bundle filtering for performance

### Response Size
- Minimal data payload
- Fast response times
- Optimized for mobile applications

## Dependencies

### Database Tables
- `account` - Main account information
- `vehicle` - Vehicle details
- `body_type` - Vehicle body type definitions
- `bundle_subscriptions` - Bundle subscription data
- `toll_bundles` - Bundle definitions

### Models Used
- `Account` - Account management
- `Vehicle` - Vehicle information
- `BodyType` - Body type definitions
- `BundleSubscription` - Bundle subscriptions
- `TollBundle` - Bundle definitions

## Testing

### Test Cases

1. **Valid Account Search**
   - Search with valid account number
   - Verify simplified response structure
   - Check data accuracy

2. **Invalid Account Search**
   - Search with non-existent account
   - Verify error response format
   - Check error message accuracy

3. **Validation Testing**
   - Test with missing account_no
   - Test with invalid account_no format
   - Verify validation error responses

4. **Edge Cases**
   - Account with no vehicles
   - Account with no active bundles
   - Account with multiple vehicles

### Sample Test Data

```json
{
    "account_no": "TEST001"
}
```

## Integration Examples

### cURL Example

```bash
curl -X POST "https://api.example.com/api/search-account-details" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -d '{
         "account_no": "ACC001"
     }'
```

### JavaScript Example

```javascript
const response = await fetch('/api/search-account-details', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_TOKEN'
    },
    body: JSON.stringify({
        account_no: 'ACC001'
    })
});

const data = await response.json();
console.log(data);
```

### PHP Example

```php
$response = Http::withHeaders([
    'Authorization' => 'Bearer YOUR_TOKEN'
])->post('/api/search-account-details', [
    'account_no' => 'ACC001'
]);

$data = $response->json();
```

## Monitoring and Logging

### Logged Information
- Request details (IP, user agent, parameters)
- Account lookup results
- Error occurrences with stack traces
- Performance metrics
- Data retrieval statistics

### Monitoring Metrics
- API response times
- Error rates
- Account search frequency
- Database query performance
- Memory usage

## Version History

- **v2.0** - Simplified response structure
- Reduced payload size for better performance
- Focus on essential account, vehicle, and active bundle data
- Optimized for mobile and web applications

- **v1.0** - Initial implementation with comprehensive account details
- Support for account, vehicle, transaction, and activity data
- Structured response format with summary statistics 