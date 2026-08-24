<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 1500 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# BCMS Pro

A Laravel-based Bridge Control Management System.

## API Endpoints

### Bill Request API

#### POST /api/post-bill

Creates a new bill request for bundle subscriptions.

**Authentication:** Required (Bearer Token)

**Request Body:**
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

**Parameters:**
- `bill_desc` (string, required): Description of the bill
- `phone` (string, required): Phone number of the account holder
- `account_no` (string, required): Account number (must exist in account table)
- `vehicle_id` (numeric, required): Vehicle ID (must exist in vehicle table)
- `plate_no` (string, required): Vehicle plate number (must match vehicle_id)
- `bundle_id` (numeric, required): Bundle type (1=daily, 2=weekly, 3=monthly)

**Note:** The `bill_amount` is automatically calculated based on the vehicle's body type and bundle type from the price list.

**Response (Success):**
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

**Response (Error):**
```json
{
    "success": false,
    "message": "Validation Error: The bill_amount field is required."
}
```

**Validation Rules:**
- Validates that all required fields are present
- Checks if vehicle exists and plate number matches
- Automatically calculates bill amount from price list based on vehicle's body type and bundle type
- Checks for outstanding bills for the same plate number
- Validates bundle_id is one of: 1, 2, or 3

**Business Logic:**
1. Validates input parameters
2. Checks vehicle and plate number match
3. Calculates bill amount from price list based on body type and bundle type
4. Checks for outstanding bills
5. Creates bridge bill record with calculated amount
6. Sends request to GePG system
7. Returns control number and calculated amount on success

**Error Handling:**
- Returns appropriate error messages for validation failures
- Handles GePG integration errors
- Uses database transactions for data consistency
- Logs errors for debugging
