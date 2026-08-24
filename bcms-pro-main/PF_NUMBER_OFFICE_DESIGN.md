# PF Number Generation by Office - Design Document

## Overview
This document outlines the design for office-based PF number generation. The system will automatically generate PF numbers for employees in certain offices (e.g., Nyerere Bridge) but require manual entry for others (e.g., Main Scheme).

---

## 1. Database Structure

### Table: `bridge_office` (bcmis2 connection)

```sql
CREATE TABLE bridge_office (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    office_name VARCHAR(255) NOT NULL COMMENT 'Office name (e.g., Nyerere Bridge, Main Scheme)',
    office_code VARCHAR(50) UNIQUE COMMENT 'Short code/identifier',
    auto_generate_pf BOOLEAN DEFAULT FALSE COMMENT 'TRUE = auto-generate PF, FALSE = manual entry required',
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(50) NULL,
    created_at TIMESTAMP NULL,
    modified_by VARCHAR(50) NULL,
    modified_at TIMESTAMP NULL,
    
    INDEX idx_office_code (office_code),
    INDEX idx_auto_generate_pf (auto_generate_pf),
    INDEX idx_is_active (is_active)
);
```

### Sample Data

| id | office_name | office_code | auto_generate_pf | is_active |
|----|-------------|-------------|------------------|-----------|
| 1  | Nyerere Bridge | NYERERE | TRUE | TRUE |
| 2  | Main Scheme | MAIN_SCHEME | FALSE | TRUE |
| 3  | Regional Office | REGIONAL | FALSE | TRUE |

### Relationship
- `bridge_employment_details.officeid` → `bridge_office.id` (foreign key)

---

## 2. Backend Logic Flow

### 2.1 Employee Registration Flow

```
┌─────────────────────────────────────────────────────────────┐
│ POST /api/bridge-employees/register                        │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │ Validate Request Data                 │
        │ - Required fields                     │
        │ - officeid (optional but recommended)│
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │ Check Office Configuration             │
        │ - Query bridge_office by officeid     │
        │ - Get auto_generate_pf flag            │
        └───────────────────────────────────────┘
                            │
                ┌───────────┴───────────┐
                │                       │
                ▼                       ▼
    ┌───────────────────┐    ┌───────────────────┐
    │ auto_generate_pf  │    │ auto_generate_pf  │
    │ = TRUE           │    │ = FALSE           │
    │ (Nyerere Bridge) │    │ (Main Scheme)     │
    └───────────────────┘    └───────────────────┘
                │                       │
                │                       │
                ▼                       ▼
    ┌───────────────────┐    ┌───────────────────┐
    │ pfno: NULL        │    │ pfno: REQUIRED     │
    │ (will be generated│    │ (must be provided)│
    │  on approval)     │    │                    │
    └───────────────────┘    └───────────────────┘
                │                       │
                └───────────┬───────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │ Create Employee Record                │
        │ - bridge_employee                     │
        │ - bridge_employment_details           │
        │ - bridge_employee_status (pending)     │
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │ Return Response                        │
        │ - Employee data                        │
        │ - Approval status                       │
        └───────────────────────────────────────┘
```

### 2.2 Employee Approval Flow

```
┌─────────────────────────────────────────────────────────────┐
│ POST /api/employee-approvals/{id}/approve-creation          │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │ Get Employee & Office Info            │
        │ - Load bridge_employee                │
        │ - Load bridge_employment_details       │
        │ - Load bridge_office by officeid      │
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │ Check auto_generate_pf Flag            │
        └───────────────────────────────────────┘
                            │
                ┌───────────┴───────────┐
                │                       │
                ▼                       ▼
    ┌───────────────────┐    ┌───────────────────┐
    │ auto_generate_pf   │    │ auto_generate_pf   │
    │ = TRUE             │    │ = FALSE           │
    └───────────────────┘    └───────────────────┘
                │                       │
                │                       │
                ▼                       ▼
    ┌───────────────────┐    ┌───────────────────┐
    │ Check if pfno      │    │ Validate pfno     │
    │ already exists      │    │ is provided       │
    └───────────────────┘    └───────────────────┘
                │                       │
                │                       │
                ▼                       ▼
    ┌───────────────────┐    ┌───────────────────┐
    │ If NULL: Generate │    │ If NULL: ERROR     │
    │ If exists: Use it │    │ If exists: Use it  │
    │ (NB0001, NB0002)  │    │ (validate unique)  │
    └───────────────────┘    └───────────────────┘
                │                       │
                └───────────┬───────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │ Update Records                         │
        │ - bridge_employee.pfno                 │
        │ - auth_user.pf_number                  │
        │ - bridge_employee_status (ACTIVE)      │
        └───────────────────────────────────────┘
```

---

## 3. Frontend UI/UX Design

### 3.1 Employee Registration Form

```
┌─────────────────────────────────────────────────────────────┐
│                    Register New Employee                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Personal Information                                        │
│  ┌────────────────────────────────────────────────────┐   │
│  │ First Name:        [________________]                │   │
│  │ Middle Name:       [________________]                │   │
│  │ Surname:           [________________]                │   │
│  │ National ID:       [________________] *              │   │
│  │ Email:             [________________]                │   │
│  │ Mobile:            [________________]                │   │
│  └────────────────────────────────────────────────────┘   │
│                                                              │
│  Employment Information                                      │
│  ┌────────────────────────────────────────────────────┐   │
│  │ Office:            [▼ Select Office...] *            │   │
│  │                                                      │   │
│  │ ┌──────────────────────────────────────────────┐   │   │
│  │ │ When office is selected, show dynamic PF field│   │   │
│  │ └──────────────────────────────────────────────┘   │   │
│  │                                                      │   │
│  │ ┌─ SCENARIO 1: Nyerere Bridge Selected ─────────┐  │   │
│  │ │ PF Number:     [Auto-generated after approval] │  │   │
│  │ │                (disabled, grayed out)          │  │   │
│  │ │                ℹ️ PF will be generated         │  │   │
│  │ │                automatically upon approval      │  │   │
│  │ └───────────────────────────────────────────────┘  │   │
│  │                                                      │   │
│  │ ┌─ SCENARIO 2: Main Scheme Selected ─────────────┐ │   │
│  │ │ PF Number:     [________________] *             │ │   │
│  │ │                (required, enabled)              │ │   │
│  │ │                ⚠️ Enter the PF number used      │ │   │
│  │ │                in Main Scheme office            │ │   │
│  │ └───────────────────────────────────────────────┘ │   │
│  │                                                      │   │
│  │ Position:         [________________]                │   │
│  │ Employment Date: [____/____/____]                  │   │
│  │ Contract Type:   [________________]                │   │
│  └────────────────────────────────────────────────────┘   │
│                                                              │
│  [Cancel]                              [Submit for Approval]│
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 Frontend Validation Logic

```javascript
// Pseudo-code for frontend validation

function validateEmployeeForm(formData) {
    const errors = {};
    
    // Basic validations
    if (!formData.fname) errors.fname = 'First name is required';
    if (!formData.sname) errors.sname = 'Surname is required';
    if (!formData.national_id) errors.national_id = 'National ID is required';
    
    // Office-based PF validation
    if (formData.officeid) {
        const office = getOfficeById(formData.officeid);
        
        if (office.auto_generate_pf === false) {
            // Office requires manual PF entry
            if (!formData.pfno || formData.pfno.trim() === '') {
                errors.pfno = 'PF Number is required for ' + office.office_name;
            } else {
                // Validate PF format (if needed)
                if (!isValidPFFormat(formData.pfno)) {
                    errors.pfno = 'Invalid PF number format';
                }
            }
        } else {
            // Office auto-generates PF - clear any entered value
            formData.pfno = null;
        }
    }
    
    return errors;
}

// Office selection handler
function onOfficeChange(officeId) {
    const office = getOfficeById(officeId);
    const pfField = document.getElementById('pfno');
    
    if (office.auto_generate_pf) {
        // Disable PF field
        pfField.disabled = true;
        pfField.value = '';
        pfField.placeholder = 'Auto-generated after approval';
        showInfoMessage('PF will be generated automatically for ' + office.office_name);
    } else {
        // Enable PF field
        pfField.disabled = false;
        pfField.required = true;
        pfField.placeholder = 'Enter PF number used in ' + office.office_name;
        showWarningMessage('PF number is required for ' + office.office_name);
    }
}
```

### 3.3 Approval Screen (Checker View)

```
┌─────────────────────────────────────────────────────────────┐
│              Pending Employee Approvals                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Employee: John Doe                                    │  │
│  │ National ID: 1234567890123456                         │  │
│  │ Office: Main Scheme                                   │  │
│  │                                                       │  │
│  │ ┌─ SCENARIO 1: Nyerere Bridge ─────────────────────┐ │  │
│  │ │ PF Number: [NB0001] (auto-generated)            │ │  │
│  │ │ Status: ✓ Ready to approve                      │ │  │
│  │ └─────────────────────────────────────────────────┘ │  │
│  │                                                       │  │
│  │ ┌─ SCENARIO 2: Main Scheme ───────────────────────┐ │  │
│  │ │ PF Number: [MS-12345] (provided by user)        │ │  │
│  │ │ Status: ✓ Ready to approve                      │ │  │
│  │ └─────────────────────────────────────────────────┘ │  │
│  │                                                       │  │
│  │ ┌─ SCENARIO 3: Main Scheme (Missing PF) ─────────┐ │  │
│  │ │ PF Number: [MISSING]                            │ │  │
│  │ │ Status: ⚠️ Cannot approve - PF required         │ │  │
│  │ │ [Edit Employee] [Reject Request]                 │ │  │
│  │ └─────────────────────────────────────────────────┘ │  │
│  │                                                       │  │
│  │ Comments: [_____________________________]            │  │
│  │                                                       │  │
│  │ [Reject]                              [Approve]      │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 4. API Endpoints & Responses

### 4.1 Get Offices List

**Endpoint:** `GET /api/bridge-offices`

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

### 4.2 Register Employee (Nyerere Bridge - Auto Generate)

**Request:**
```json
POST /api/bridge-employees/register
{
    "fname": "John",
    "sname": "Doe",
    "national_id": "1234567890123456",
    "email": "john.doe@example.com",
    "mobile": "255712345678",
    "officeid": 1,
    "positionid": 5,
    "empdate": "2026-01-01"
    // Note: pfno is NOT provided
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "employee": {
            "national_id": "1234567890123456",
            "fname": "John",
            "sname": "Doe",
            "pfno": null,
            "office": {
                "id": 1,
                "office_name": "Nyerere Bridge",
                "auto_generate_pf": true
            }
        },
        "approval_status": {
            "status_id": 1,
            "status": "pending_creation",
            "message": "Awaiting approval"
        }
    },
    "message": "Bridge employee registered successfully and submitted for approval"
}
```

### 4.3 Register Employee (Main Scheme - Manual PF)

**Request:**
```json
POST /api/bridge-employees/register
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

**Response:**
```json
{
    "success": true,
    "data": {
        "employee": {
            "national_id": "9876543210987654",
            "fname": "Jane",
            "sname": "Smith",
            "pfno": "MS-12345",
            "office": {
                "id": 2,
                "office_name": "Main Scheme",
                "auto_generate_pf": false
            }
        },
        "approval_status": {
            "status_id": 2,
            "status": "pending_creation",
            "message": "Awaiting approval"
        }
    },
    "message": "Bridge employee registered successfully and submitted for approval"
}
```

### 4.4 Register Employee (Main Scheme - Missing PF - ERROR)

**Request:**
```json
POST /api/bridge-employees/register
{
    "fname": "Jane",
    "sname": "Smith",
    "national_id": "9876543210987654",
    "officeid": 2
    // Note: pfno is missing but required for Main Scheme
}
```

**Response:**
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

### 4.5 Approve Employee Creation (Nyerere Bridge)

**Request:**
```json
POST /api/employee-approvals/1/approve-creation
{
    "comments": "Approved after verification"
}
```

**Response:**
```json
{
    "success": true,
    "data": null,
    "message": "Employee creation approved successfully. PF Number NB0001 has been generated and assigned."
}
```

**What happens:**
- PF Number `NB0001` is generated
- `bridge_employee.pfno` = `NB0001`
- `auth_user.pf_number` = `NB0001`
- Employee status = `ACTIVE`

### 4.6 Approve Employee Creation (Main Scheme - Missing PF - ERROR)

**Request:**
```json
POST /api/employee-approvals/2/approve-creation
{
    "comments": "Trying to approve"
}
```

**Response:**
```json
{
    "success": false,
    "data": null,
    "message": "Cannot approve employee creation. PF Number is required for Main Scheme office but was not provided. Please update the employee record with a valid PF number before approval.",
    "code": 422
}
```

---

## 5. Validation Rules Summary

### Registration Validation

| Office Type | PF Field Required? | PF Field Editable? | Behavior |
|------------|-------------------|-------------------|----------|
| **Auto-generate (Nyerere Bridge)** | No | No (disabled) | PF will be generated on approval |
| **Manual (Main Scheme)** | Yes | Yes | User must provide existing PF |

### Approval Validation

| Office Type | Current PF Status | Action |
|------------|------------------|--------|
| **Auto-generate** | NULL | Generate new PF (NB0001, NB0002, ...) |
| **Auto-generate** | Already exists | Use existing PF (don't overwrite) |
| **Manual** | NULL | **ERROR: Cannot approve** |
| **Manual** | Provided | Validate uniqueness, then approve |

---

## 6. Database Seeder Example

```php
// database/seeders/OfficeSeeder.php

DB::connection('bcmis2')->table('bridge_office')->insert([
    [
        'office_name' => 'Nyerere Bridge',
        'office_code' => 'NYERERE',
        'auto_generate_pf' => true,
        'description' => 'Main office - auto-generates PF numbers',
        'is_active' => true,
        'created_at' => now(),
    ],
    [
        'office_name' => 'Main Scheme',
        'office_code' => 'MAIN_SCHEME',
        'auto_generate_pf' => false,
        'description' => 'Requires manual PF number entry',
        'is_active' => true,
        'created_at' => now(),
    ],
]);
```

---

## 7. Implementation Checklist

- [ ] Create `bridge_office` table migration
- [ ] Create `BridgeOffice` model
- [ ] Create office seeder with Nyerere Bridge and Main Scheme
- [ ] Update `ReferralApprovalService::approveCreation()` to check office
- [ ] Update `BridgeEmployeeController::registerBridgeEmployee()` validation
- [ ] Create `BridgeOfficeController` with GET endpoint
- [ ] Update frontend registration form with office dropdown
- [ ] Add dynamic PF field behavior (enable/disable based on office)
- [ ] Update approval screen to show PF status
- [ ] Add validation messages for missing PF

---

## 8. Questions to Clarify

1. **Office Identification:** How are offices currently identified? Is `officeid` in `bridge_employment_details` already linked to an office table, or is it just an integer?

2. **PF Format:** What format should PF numbers follow for Main Scheme? (e.g., `MS-12345`, `MS12345`, or any format?)

3. **Existing Data:** Do existing employees in `bridge_employment_details` have `officeid` values? Do we need to migrate existing data?

4. **Office Management:** Should there be an admin interface to manage offices and toggle `auto_generate_pf`?

5. **Multiple Offices:** Can an employee belong to multiple offices? If so, which office's rule applies?

---

**Ready to proceed with implementation?** Let me know if you'd like any changes to this design!

