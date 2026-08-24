# PF Number Office-Based Generation - Implementation Summary

## ✅ Implementation Complete

All backend changes have been implemented successfully. The system now supports office-based PF number generation.

---

## 📋 What Was Implemented

### 1. Database Structure
- ✅ Created `bridge_office` table migration
- ✅ Created `BridgeOffice` model with scopes
- ✅ Created `BridgeOfficeSeeder` with Nyerere Bridge and Main Scheme

### 2. API Endpoints
- ✅ `GET /api/bridge-offices` - Get all active offices
- ✅ `GET /api/bridge-offices/{id}` - Get specific office

### 3. Backend Logic Updates

#### Registration (`BridgeEmployeeController`)
- ✅ Validates PF requirement based on selected office
- ✅ Auto-generate offices: PF field is optional (will be generated on approval)
- ✅ Manual-entry offices: PF field is **required** with clear error messages
- ✅ Creates `bridge_employment_details` when `officeid` is provided

#### Approval (`ReferralApprovalService`)
- ✅ Checks office configuration from `bridge_employment_details`
- ✅ Auto-generate offices: Generates PF if missing (NB0001, NB0002, ...)
- ✅ Manual-entry offices: **Requires PF** - throws exception if missing
- ✅ Validates PF uniqueness for manual-entry offices
- ✅ Updates both `bridge_employee.pfno` and `auth_user.pf_number`

---

## 🗄️ Database Setup Required

### Step 1: Run Migration
```bash
php artisan migrate
```

### Step 2: Seed Offices
```bash
php artisan db:seed --class=BridgeOfficeSeeder
```

Or add to `DatabaseSeeder.php`:
```php
$this->call([
    BridgeOfficeSeeder::class,
]);
```

---

## 📝 Office Configuration

### Current Offices (from seeder):

| Office Name | Office Code | Auto-Generate PF |
|------------|-------------|------------------|
| Nyerere Bridge | NYERERE | ✅ Yes |
| Main Scheme | MAIN_SCHEME | ❌ No (manual entry) |

### To Add More Offices:
1. Insert into `bridge_office` table:
```sql
INSERT INTO bridge_office (office_name, office_code, auto_generate_pf, is_active, created_at)
VALUES ('Office Name', 'OFFICE_CODE', true/false, true, NOW());
```

---

## 🔄 API Usage Examples

### 1. Get Offices List
```http
GET /api/bridge-offices
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "office_name": "Nyerere Bridge",
            "office_code": "NYERERE",
            "auto_generate_pf": true,
            "is_active": true
        },
        {
            "id": 2,
            "office_name": "Main Scheme",
            "office_code": "MAIN_SCHEME",
            "auto_generate_pf": false,
            "is_active": true
        }
    ],
    "message": "Offices retrieved successfully"
}
```

### 2. Register Employee (Nyerere Bridge - Auto Generate)
```http
POST /api/bridge-employees/register
Content-Type: application/json

{
    "fname": "John",
    "sname": "Doe",
    "national_id": "1234567890123456",
    "email": "john.doe@example.com",
    "mobile": "255712345678",
    "officeid": 1,
    "positionid": 5,
    "empdate": "2026-01-01"
}
```

**Note:** `pfno` is NOT required - will be generated on approval.

### 3. Register Employee (Main Scheme - Manual PF)
```http
POST /api/bridge-employees/register
Content-Type: application/json

{
    "fname": "Jane",
    "sname": "Smith",
    "national_id": "9876543210987654",
    "email": "jane.smith@example.com",
    "mobile": "255798765432",
    "officeid": 2,
    "pfno": "MS-12345",
    "positionid": 3,
    "empdate": "2026-01-01"
}
```

**Note:** `pfno` is **REQUIRED** for Main Scheme.

### 4. Register Employee (Main Scheme - Missing PF - ERROR)
```http
POST /api/bridge-employees/register
Content-Type: application/json

{
    "fname": "Jane",
    "sname": "Smith",
    "national_id": "9876543210987654",
    "officeid": 2
}
```

**Response (422):**
```json
{
    "success": false,
    "data": {
        "pfno": [
            "PF Number is required for Main Scheme office. This office does not auto-generate PF numbers."
        ]
    },
    "message": "Validation failed",
    "code": 422
}
```

### 5. Approve Employee Creation
```http
POST /api/employee-approvals/{statusId}/approve-creation
Content-Type: application/json

{
    "comments": "Approved after verification"
}
```

**Behavior:**
- **Nyerere Bridge**: Generates PF (NB0001, NB0002, ...)
- **Main Scheme**: Uses provided PF, validates uniqueness
- **Main Scheme (no PF)**: Returns error, cannot approve

---

## 🎨 Frontend Integration Guide

### 1. Fetch Offices on Form Load
```javascript
// Fetch offices for dropdown
fetch('/api/bridge-offices')
    .then(res => res.json())
    .then(data => {
        // Populate office dropdown
        const offices = data.data;
        // ...
    });
```

### 2. Handle Office Selection
```javascript
function onOfficeChange(officeId) {
    const office = offices.find(o => o.id === officeId);
    const pfField = document.getElementById('pfno');
    
    if (office.auto_generate_pf) {
        // Disable PF field
        pfField.disabled = true;
        pfField.value = '';
        pfField.required = false;
        pfField.placeholder = 'Auto-generated after approval';
        showInfo('PF will be generated automatically for ' + office.office_name);
    } else {
        // Enable PF field
        pfField.disabled = false;
        pfField.required = true;
        pfField.placeholder = 'Enter PF number used in ' + office.office_name;
        showWarning('PF number is required for ' + office.office_name);
    }
}
```

### 3. Form Validation
```javascript
function validateForm() {
    const officeId = document.getElementById('officeid').value;
    const pfno = document.getElementById('pfno').value;
    const office = offices.find(o => o.id === officeId);
    
    if (office && !office.auto_generate_pf) {
        if (!pfno || pfno.trim() === '') {
            showError('PF Number is required for ' + office.office_name);
            return false;
        }
    }
    
    return true;
}
```

---

## ✅ Testing Checklist

- [ ] Run migration: `php artisan migrate`
- [ ] Seed offices: `php artisan db:seed --class=BridgeOfficeSeeder`
- [ ] Test GET `/api/bridge-offices` endpoint
- [ ] Test employee registration with Nyerere Bridge (no PF)
- [ ] Test employee registration with Main Scheme (with PF)
- [ ] Test employee registration with Main Scheme (missing PF - should fail)
- [ ] Test approval for Nyerere Bridge employee (should generate PF)
- [ ] Test approval for Main Scheme employee (should use provided PF)
- [ ] Test approval for Main Scheme employee (missing PF - should fail)
- [ ] Verify `bridge_employee.pfno` is set correctly
- [ ] Verify `auth_user.pf_number` is set correctly

---

## 🔍 Key Files Modified/Created

### Created:
- `database/migrations/2026_01_30_000000_create_bridge_office_table.php`
- `app/Models/Bms/BridgeOffice.php`
- `app/Http/Controllers/Bms/BridgeOfficeController.php`
- `database/seeders/BridgeOfficeSeeder.php`

### Modified:
- `app/Services/ReferralApprovalService.php` - Updated `approveCreation()` method
- `app/Http/Controllers/Bms/BridgeEmployeeController.php` - Added office-based validation
- `routes/api.php` - Added office routes

---

## 📚 Documentation

See `PF_NUMBER_OFFICE_DESIGN.md` for complete design documentation including:
- Database schema
- Frontend UI mockups
- API endpoint details
- Validation rules

---

## 🚀 Next Steps

1. **Run migrations and seeders**
2. **Test the API endpoints**
3. **Update frontend** to use the new office-based logic
4. **Monitor logs** for any issues during approval process

---

**Implementation Date:** January 30, 2026
**Status:** ✅ Complete and Ready for Testing

