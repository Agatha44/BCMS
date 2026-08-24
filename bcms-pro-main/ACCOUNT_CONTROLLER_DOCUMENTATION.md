# Account Controller Documentation

## Overview
The Account Controller provides functionality for portal registration in the BCMS Pro system. It has been converted from Yii2 to Laravel and includes the `portalRegistration` method.

## Endpoint

### Portal Registration
**POST** `/api/portal-registration`

Registers a new account or links a vehicle to an existing account.

#### Request Parameters
```json
{
    "phone": "string (required)",
    "plate_no": "string (required)", 
    "bundle_id": "integer (required) - 1=daily, 2=weekly, 3=monthly",
    "nida": "string (required)",
    "first_name": "string (required)",
    "middle_name": "string (optional)",
    "surname": "string (required)",
    "email": "string (required)",
    "password_hash": "string (required)",
    "created_by": "integer (required)"
}
```

#### Response Format

**Success Response (New Account Created):**
```json
{
    "success": true,
    "status_code": 1,
    "data": {
        "vehicle": {
            "id": 123,
            "plate_no": "ABC123",
            "body_type": {
                "id": 1,
                "name": "Sedan",
                "description": "Sedan vehicle type"
            },
            "account_no": "ACC001",
            "image": null,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        },
        "owner": {
            "account_no": "ACC001",
            "first_name": "John",
            "surname": "Doe",
            "full_name": "John Doe",
            "phone": "255123456789",
            "email": "john.doe@example.com",
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        },
        "bundles": [
            {
                "bundle_id": 1,
                "bundle_name": "Daily Bundle",
                "bundle_description": "Daily Bundle Subscription",
                "amount": 5000,
                "currency": "TZS"
            },
            {
                "bundle_id": 2,
                "bundle_name": "Weekly Bundle",
                "bundle_description": "Weekly Bundle Subscription",
                "amount": 25000,
                "currency": "TZS"
            },
            {
                "bundle_id": 3,
                "bundle_name": "Monthly Bundle",
                "bundle_description": "Monthly Bundle Subscription",
                "amount": 100000,
                "currency": "TZS"
            }
        ],
        "price_list": {
            "id": 1,
            "body_type_id": 1,
            "regular_amount": 2000,
            "daily_bundle_amount": 5000,
            "weekly_bundle_amount": 25000,
            "monthly_bundle_amount": 100000,
            "status": "1"
        }
    },
    "message": "Vehicle successfully associated with a new account"
}
```

**Success Response (Existing Account):**
```json
{
    "success": true,
    "status_code": 1,
    "data": {
        "vehicle": {
            "id": 123,
            "plate_no": "ABC123",
            "body_type": {
                "id": 1,
                "name": "Sedan",
                "description": "Sedan vehicle type"
            },
            "account_no": "ACC001",
            "image": null,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        },
        "owner": {
            "account_no": "ACC001",
            "first_name": "John",
            "surname": "Doe",
            "full_name": "John Doe",
            "phone": "255123456789",
            "email": "john.doe@example.com",
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        },
        "bundles": [
            {
                "bundle_id": 1,
                "bundle_name": "Daily Bundle",
                "bundle_description": "Daily Bundle Subscription",
                "amount": 5000,
                "currency": "TZS"
            },
            {
                "bundle_id": 2,
                "bundle_name": "Weekly Bundle",
                "bundle_description": "Weekly Bundle Subscription",
                "amount": 25000,
                "currency": "TZS"
            },
            {
                "bundle_id": 3,
                "bundle_name": "Monthly Bundle",
                "bundle_description": "Monthly Bundle Subscription",
                "amount": 100000,
                "currency": "TZS"
            }
        ],
        "price_list": {
            "id": 1,
            "body_type_id": 1,
            "regular_amount": 2000,
            "daily_bundle_amount": 5000,
            "weekly_bundle_amount": 25000,
            "monthly_bundle_amount": 100000,
            "status": "1"
        }
    },
    "message": "Vehicle successfully associated with an existing Account"
}
```

**Error Response:**
```json
{
    "success": false,
    "status_code": 0,
    "message": "Error message here",
    "data": {} // Optional error details
}
```

#### Business Logic

1. **Account Check**: The system first checks if an account exists with the provided phone number
2. **Vehicle Validation**: Validates that the vehicle exists and is not already associated with an account
3. **Body Type Validation**: Checks if the vehicle's body type is allowed for the service
4. **Bundle Pricing**: Retrieves the appropriate bundle pricing based on the vehicle's body type
5. **Account Creation** (if new):
   - Creates a new account with the provided details
   - Hashes the password using Laravel's Hash facade
   - Generates an account number
   - Sends SMS notification
   - Creates a card sequence number
   - Links the vehicle to the account
6. **Vehicle Linking** (if existing account):
   - Links the vehicle to the existing account
   - Creates a card sequence number
   - Updates vehicle details
7. **Response Format**: Returns detailed vehicle and account information in the same format as `getVehicleBundleInfo`

#### Error Handling

The endpoint handles various error scenarios:
- Validation errors for required fields
- Vehicle not found
- Body type not found
- Bundle not found
- Price list not found
- Body type not allowed for service
- Vehicle already associated with an account
- Database transaction failures

#### Dependencies

The controller uses the following models:
- `Account` - User account management
- `Vehicle` - Vehicle information
- `BodyType` - Vehicle body type definitions
- `TollBundle` - Bundle subscription types
- `PriceList` - Pricing information
- `CardSequence` - Card number generation
- `IdsMessages` - SMS logging

#### Database Transactions

All database operations are wrapped in transactions to ensure data consistency. If any operation fails, the entire transaction is rolled back.

#### SMS Integration

The system automatically sends SMS notifications when:
- A new account is created
- A vehicle is successfully linked to an account

The SMS includes:
- Account number
- User's full name
- Default password (12345)
- Portal URL

## Usage Example

```bash
curl -X POST http://your-domain.com/api/portal-registration \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "255123456789",
    "plate_no": "ABC123",
    "bundle_id": 1,
    "nida": "1234567890123456",
    "first_name": "John",
    "surname": "Doe",
    "email": "john.doe@example.com",
    "password_hash": "mypassword123",
    "created_by": 1
  }'
```

## Notes

- This endpoint is focused on registration only and does not create bills
- The system uses Laravel's built-in validation and error handling
- All timestamps are handled using Laravel's `now()` helper
- Password hashing is done using Laravel's `Hash::make()` method
- The endpoint follows RESTful conventions and returns consistent JSON responses
- The response format matches the `getVehicleBundleInfo` endpoint for consistency 