# BCMS Pro API - Postman Collection Setup Guide

## 📋 Overview

This guide will help you set up and use the **BCMS Pro API** Postman collection, which includes all endpoints for the Bridge Control Management System Pro.

## 🚀 Quick Start

### 1. Import the Collection

1. **Download the Collection File**
   - Save `BCMS_Pro_Clean_API.postman_collection.json` to your computer

2. **Import into Postman**
   - Open Postman
   - Click **Import** button (top left)
   - Drag and drop the JSON file or click **Upload Files**
   - Select the `BCMS_Pro_Clean_API.postman_collection.json` file
   - Click **Import**

### 2. Set Up Environment Variables

1. **Create Environment**
   - Click the **Environments** tab (left sidebar)
   - Click **+** to create a new environment
   - Name it: `BCMS Pro - Local` (or your preferred name)

2. **Add Variables**
   - Add these variables to your environment:

   | Variable Name | Initial Value | Description |
   |---------------|---------------|-------------|
   | `base_url` | `http://localhost:8000` | Your Laravel API base URL |
   | `bearer_token` | (leave empty) | Will be auto-populated after login |

3. **Select Environment**
   - In the top-right dropdown, select your newly created environment

## 🔐 Authentication Setup

### Automatic Token Management

The collection includes automatic Bearer token management:

1. **Login First**
   - Go to **🔐 Authentication** folder
   - Run **Login - Get Bearer Token**
   - Update the request body with your credentials:
   ```json
   {
       "username": "your_actual_username",
       "password": "your_actual_password"
   }
   ```

3. **Expected Response Format**
   The login endpoint returns a response in this format:
   ```json
   {
       "success": true,
       "status_code": 1,
       "data": {
           "token": "591|aYwrCrYYA7PfHeVZK7guPaRWlPLWieddi3Md2j9q",
           "user": {
               "id": 33,
               "username": "emmanuel.mdegipala",
               "first_name": "Emmanuel",
               "surname": "Mdegipala",
               "email": "emmanuel.mdegipala@gmail.com"
           },
           "roles": [...]
       },
       "message": "Login successful"
   }
   ```

2. **Token Auto-Save**
   - The login request includes a test script that automatically saves the Bearer token
   - The script extracts the token from `response.data.token` in the login response
   - After successful login, the token is stored in the `bearer_token` environment variable
   - All subsequent authenticated requests will use this token automatically

## 📁 Collection Structure

The collection is organized into logical folders:

### 🔐 Authentication
- **Login - Get Bearer Token**: Authenticate and get Bearer token

### 📦 Bundle Management
- **Get All Bundle Types**: List available bundle types
- **Get Vehicle Price**: Get pricing for specific vehicle
- **Create Bill Request**: Create new bill for bundle subscription

### 🌐 Portal Services
- **Bridge Account Management**: Account details, subscription management
- **User Data**: Bundles, vehicles, top-ups for authenticated users

### 🚗 Vehicle Management
- **Get Vehicle Data by Plate**: Retrieve vehicle information

### 🏢 Booth Operations
- **Counter Management**: Open/close counters
- **Vehicle Processing**: Detection, pay-and-go, gate control
- **Receipt Management**: Reprint receipts

### 📊 Reports & Analytics
- **Collection Reports**: Toll, incident, overload, event collections
- **Financial Reports**: Monthly/yearly summaries
- **Account Reports**: Passage reports for specific accounts

### ⚙️ System Configuration
- **User Management**: List and add users
- **System Data**: Lanes, body types, report categories
- **Statistics**: Report statistics

### 📱 Commuter Services
- **Account Details**: Commuter information and vehicles
- **Transaction History**: Bundle transactions and top-ups
- **Active Services**: Current bundles and control numbers

## 🧪 Testing Workflows

### Basic Testing Flow

1. **Authentication**
   ```
   Login - Get Bearer Token → Verify token is saved
   ```

2. **Bundle Management Flow**
   ```
   Get All Bundle Types → Get Vehicle Price → Create Bill Request
   ```

3. **Portal Services Flow**
   ```
   Get Bridge Account Details → Subscribe to Bridge Service → Get User Bundles
   ```

4. **Booth Operations Flow**
   ```
   Open Counter → Record Vehicle Detection → Pay and Go → Close Counter
   ```

### Advanced Testing Scenarios

#### Scenario 1: Complete Bundle Subscription
1. Login to get Bearer token
2. Get available bundle types
3. Get vehicle pricing
4. Create bill request
5. Check control number status

#### Scenario 2: Booth Operations
1. Open counter for a lane
2. Record vehicle detection
3. Process pay-and-go transaction
4. Open toll gate
5. Reprint receipt if needed
6. Close counter

#### Scenario 3: Reporting
1. Generate toll collection report
2. Generate incident collection report
3. Generate monthly/yearly summaries
4. Generate account-specific reports

## 🔧 Configuration Options

### Environment Variables

You can create multiple environments for different setups:

#### Local Development
```
base_url: http://localhost:8000
bearer_token: (auto-populated)
```

#### Staging Environment
```
base_url: https://staging.bcms-pro.com
bearer_token: (auto-populated)
```

#### Production Environment
```
base_url: https://api.bcms-pro.com
bearer_token: (auto-populated)
```

### Custom Headers

Some requests may require additional headers. You can add them globally:

1. **Collection Level Headers**
   - Right-click on the collection
   - Select **Edit**
   - Go to **Headers** tab
   - Add common headers like:
     - `Accept: application/json`
     - `X-Requested-With: XMLHttpRequest`

## 🐛 Troubleshooting

### Common Issues

#### 1. Authentication Errors
- **Problem**: 401 Unauthorized errors
- **Solution**: 
  - Run the login request first
  - Check that the Bearer token is properly saved
  - Verify credentials are correct

#### 2. Base URL Issues
- **Problem**: Connection refused or 404 errors
- **Solution**:
  - Verify your Laravel server is running
  - Check the `base_url` environment variable
  - Ensure the API routes are properly configured

#### 3. Validation Errors
- **Problem**: 422 Validation errors
- **Solution**:
  - Check request body format
  - Verify all required fields are provided
  - Ensure data types match expected format

#### 4. Database Errors
- **Problem**: 500 Internal Server errors
- **Solution**:
  - Check Laravel logs (`storage/logs/laravel.log`)
  - Verify database connection
  - Ensure required data exists in database

### Debug Mode

To enable detailed error responses:

1. **Laravel Configuration**
   - Set `APP_DEBUG=true` in your `.env` file
   - Restart your Laravel server

2. **Postman Console**
   - Open Postman Console (View → Show Postman Console)
   - Check for detailed error messages

## 📝 Best Practices

### 1. Environment Management
- Use separate environments for different stages
- Never commit sensitive data to version control
- Use environment variables for configuration

### 2. Request Organization
- Group related requests in folders
- Use descriptive names for requests
- Add detailed descriptions for complex endpoints

### 3. Testing Strategy
- Test authentication first
- Use realistic test data
- Test both success and error scenarios
- Verify response formats

### 4. Data Management
- Use consistent test data across requests
- Clean up test data after testing
- Document any required setup data

## 🔄 Updates and Maintenance

### Collection Updates
- Export updated collections regularly
- Version your collections
- Document breaking changes

### Environment Updates
- Update environment variables when API changes
- Maintain separate environments for different versions
- Document environment-specific configurations

## 📞 Support

For issues with the API or collection:

1. **Check Laravel Logs**: `storage/logs/laravel.log`
2. **Verify API Routes**: `php artisan route:list`
3. **Test Database Connection**: `php artisan tinker`
4. **Check Environment**: Verify `.env` configuration

## 📚 Additional Resources

- [Laravel API Documentation](https://laravel.com/docs/api)
- [Postman Learning Center](https://learning.postman.com/)
- [BCMS Pro API Documentation](README.md)
- [Test Guide](test_post_bill_api.md)

---

**Happy Testing! 🚀** 