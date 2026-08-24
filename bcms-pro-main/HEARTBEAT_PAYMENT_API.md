# Heartbeat Payment API

## Overview

The Heartbeat Payment API allows you to check the payment status of bills using different search parameters. This API supports searching by `bill_id`, `plate_no`, or `account_no` and checks both `bridge_bills` and `top_up` tables for payment completion.

## Endpoint

```
POST /api/heartbeat-payment
```

## Request Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| `bill_id` | string | No* | The bill ID to search for | "12345" |
| `plate_no` | string | No* | The plate number to search for (searches bridge_bills only) | "ABC123" |
| `account_no` | string | No* | The account number to search for (searches top_up only) | "ACC001" |

*At least one of these parameters must be provided.

### Request Examples

**Search by bill_id:**
```json
{
    "bill_id": "12345"
}
```

**Search by plate_no:**
```json
{
    "plate_no": "ABC123"
}
```

**Search by account_no:**
```json
{
    "account_no": "ACC001"
}
```

## Response Format

### Success Response - Bridge Bill (bill_id or plate_no)

```json
{
    "success": true,
    "status_code": 1,
    "data": {
        "search_type": "bill_id",
        "bill_type": "bridge_bill",
        "bill_id": 12345,
        "plate_no": "ABC123",
        "bill_desc": "Toll Fee",
        "bill_amount": 2000.00,
        "control_number": "CTRL123456",
        "bill_status": "PAID",
        "t_status": "SUCCESS",
        "error_code": "0000",
        "bill_gen_at": "2024-01-15T10:30:00.000000Z",
        "bill_exp_dt": "2024-02-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:35:00.000000Z",
        "source": "BRIDGE",
        "has_control_number": true,
        "is_paid": true,
        "is_cancelled": false,
        "payment_details": {
            "transaction_datetime": "2024-01-15T10:35:00.000000Z",
            "transaction_id": "TXN123456",
            "payment_channel": "MOBILE_MONEY",
            "psp_receipt_number": "PSP123456",
            "psp_name": "M-Pesa",
            "credit_account_number": "ACC001",
            "payment_date": "2024-01-15T10:35:00.000000Z",
            "paid_amount": 2000.00,
            "payer_name": "John Doe",
            "receipt_number": "RCP123456",
            "pay_ref_id": "REF123456"
        }
    },
    "message": "Bridge bill payment status retrieved successfully - Payment completed"
}
```

### Success Response - Top Up (account_no)

```json
{
    "success": true,
    "status_code": 1,
    "data": {
        "search_type": "account_no",
        "account_no": "ACC001",
        "bill_type": "top_up",
        "bill_id": 67890,
        "bill_desc": "Account Top-up",
        "bill_amount": 50000.00,
        "control_number": "CTRL789012",
        "bill_status": "PAID",
        "t_status": "SUCCESS",
        "error_code": "0000",
        "bill_gen_at": "2024-01-15T10:30:00.000000Z",
        "bill_exp_dt": "2024-02-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:35:00.000000Z",
        "source": "TOP_UP",
        "has_control_number": true,
        "is_paid": true,
        "is_cancelled": false,
        "payment_details": {
            "transaction_datetime": "2024-01-15T10:35:00.000000Z",
            "transaction_id": "TXN789012",
            "payment_channel": "MOBILE_MONEY",
            "psp_receipt_number": "PSP789012",
            "psp_name": "M-Pesa",
            "credit_account_number": "ACC001",
            "payment_date": "2024-01-15T10:35:00.000000Z",
            "paid_amount": 50000.00,
            "payer_name": "John Doe",
            "receipt_number": "RCP789012",
            "pay_ref_id": "REF789012"
        }
    },
    "message": "Top up bill payment status retrieved successfully by account number - Payment completed"
}
```

### Error Response

```json
{
    "success": false,
    "status_code": 0,
    "message": "No bill found for account number",
    "data": {
        "account_no": "INVALID_ACC",
        "message": "No top up bill found for the provided account number"
    }
}
```

## Business Logic

### Search Priority
1. **account_no**: Searches only in `top_up` table for the latest bill by `bill_gen_at`
2. **plate_no**: Searches only in `bridge_bills` table for the latest bill by `bill_gen_at`
3. **bill_id**: Searches first in `bridge_bills`, then in `top_up` if not found

### Payment Status Determination
- **is_paid**: `true` if `trx_dt_tm` (transaction datetime) is not null/empty
- **has_control_number**: `true` if `contr_num` is not empty
- **is_cancelled**: `true` if `bill_status` equals "CANCELLED"

### Table Selection Logic
- **account_no**: Always searches `top_up` table
- **plate_no**: Always searches `bridge_bills` table using `dist_param`
- **bill_id**: Searches both tables in order (bridge_bills first, then top_up)

## Error Handling

### Validation Errors
- **Missing parameters**: Returns error if no search parameter is provided
- **Invalid format**: Returns validation error for malformed parameters

### Business Logic Errors
- **Bill not found**: Returns specific error message with search parameter
- **Database errors**: Returns generic error with details logged

### Common Error Scenarios

```json
{
    "success": false,
    "status_code": 0,
    "message": "At least one search parameter is required",
    "data": {
        "message": "Please provide either bill_id, plate_no, or account_no parameter"
    }
}
```

```json
{
    "success": false,
    "status_code": 0,
    "message": "No bill found for bill_id",
    "data": {
        "bill_id": "99999",
        "message": "Bill ID not found in bridge_bills or top_up tables"
    }
}
```

## Use Cases

### 1. Payment Verification
- Verify if a payment has been completed for a bill
- Check payment status and transaction details
- Monitor payment processing workflow

### 2. Account Management
- Check top-up payment status by account number
- Verify account-related payment completion
- Monitor account payment processing

### 3. Vehicle Management
- Check toll payment status by plate number
- Verify vehicle-related payment completion
- Monitor vehicle payment processing

### 4. Reconciliation
- Verify payment completion for accounting purposes
- Check transaction details for reconciliation
- Monitor payment workflow status

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
- Strict validation of search parameters
- SQL injection prevention
- XSS protection

## Performance Considerations

### Database Optimization
- Efficient queries with proper indexing
- Latest bill retrieval using `orderBy('bill_gen_at', 'desc')`
- Targeted table searches based on parameter type

### Response Time
- Fast lookup for payment status
- Minimal data transfer
- Optimized for real-time monitoring

## Dependencies

### Database Tables
- `bridge_bills` - Bridge toll bills and payment information
- `top_up` - Account top-up bills and payment information

### Models Used
- Direct database queries for performance
- No model dependencies for this API

## Testing

### Test Cases

1. **Valid bill_id Search**
   - Search with valid bill ID
   - Verify response structure
   - Check payment status accuracy

2. **Valid plate_no Search**
   - Search with valid plate number
   - Verify bridge_bills table search
   - Check latest bill payment status

3. **Valid account_no Search**
   - Search with valid account number
   - Verify top_up table search
   - Check latest bill payment status

4. **Invalid Search**
   - Search with non-existent parameters
   - Verify error response format
   - Check error message accuracy

### Sample Test Data

```json
{
    "bill_id": "12345"
}
```

```json
{
    "plate_no": "ABC123"
}
```

```json
{
    "account_no": "ACC001"
}
```

## Integration Examples

### cURL Example

```bash
curl -X POST "https://api.example.com/api/heartbeat-payment" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -d '{
         "account_no": "ACC001"
     }'
```

### JavaScript Example

```javascript
const response = await fetch('/api/heartbeat-payment', {
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
])->post('/api/heartbeat-payment', [
    'account_no' => 'ACC001'
]);

$data = $response->json();
```

## Monitoring and Logging

### Logged Information
- Request details (IP, user agent, parameters)
- Search parameter and type
- Bill lookup results
- Payment status verification
- Error occurrences with stack traces
- Performance metrics

### Monitoring Metrics
- API response times
- Error rates
- Search parameter distribution
- Database query performance
- Payment status distribution

## Version History

- **v2.0** - Added account_no parameter support
- Support for searching top_up table by account number
- Enhanced search priority logic
- Improved error handling and logging

- **v1.0** - Initial implementation
- Support for bill_id and plate_no parameters
- Bridge_bills table integration
- Basic payment status checking 