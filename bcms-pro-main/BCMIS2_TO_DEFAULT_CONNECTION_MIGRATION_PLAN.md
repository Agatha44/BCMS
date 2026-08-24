# BCMIS2 → Default Connection Migration Plan

**Goal:** Consolidate all BMS / Bridge / Payroll / Overtime tables and application code onto the **default database connection** (`mysql` / `DB_*` env vars). Remove all usage of the `bcmis2` connection and schema.

**Decision:** Use the **default schema only** — not `bcmis2`.

**Status:** Plan only — not yet implemented.

---

## 1. Current state

| Concern | Today |
|---------|-------|
| Migration tracking (`migrations` table) | Default connection |
| BMS table DDL (`Schema::connection('bcmis2')`) | `bcmis2` connection (`DB2_*` env vars) |
| Eloquent models (`protected $connection = 'bcmis2'`) | 48 models |
| Raw queries (`DB::connection('bcmis2')`, `DB::table('bcmis2.x')`) | Controllers, services, commands, seeders |
| Validation (`exists:bcmis2.table`) | 8 controllers |

**Config reference:** `config/database.php` — `bcmis2` block uses `DB2_HOST`, `DB2_DATABASE`, `DB2_USERNAME`, `DB2_PASSWORD`.

---

## 2. Pre-migration discovery (run first)

### 2.1 Confirm which migrations ran

On the **default** connection:

```sql
SELECT migration FROM migrations
WHERE migration LIKE '2025_12_%'
   OR migration LIKE '2026_%'
ORDER BY migration;
```

Compare results with the 88 migration files listed in Section 3.

### 2.2 Inventory tables currently on `bcmis2`

On the `bcmis2` database (`DB2_DATABASE`):

```sql
SHOW TABLES;
```

### 2.3 Check for table name conflicts on default schema

Before switching, verify the default database does **not** already have conflicting tables (e.g. `roles`, `permissions`, `bank`, `departments`). BMS `roles` / `permissions` are separate from legacy `auth_role` / `auth_permission`, but names like `bank` or `departments` could collide.

### 2.4 Data strategy

Choose one approach:

| Option | When to use | Action |
|--------|-------------|--------|
| **A — Same physical DB** | `DB2_DATABASE` and `DB_DATABASE` are already the same (or will be aligned) | Code-only cleanup |
| **B — Separate databases** | Tables/data live only on `DB2_DATABASE` | Export from `bcmis2`, import into default, then code changes |

> **Important:** Editing migration files does **not** re-run them on existing environments. If tables exist only on `bcmis2`, data must be moved manually (Option B) before or alongside code changes.

---

## 3. Migrations to update (88 files)

All files under `database/migrations/` that reference `bcmis2`.

**Change in every file:**

- `Schema::connection('bcmis2')` → `Schema::`
- `DB::connection('bcmis2')` → `DB::`
- Update comments that reference `bcmis2` (e.g. FK comments in `bridge_employee_role`)

### 3.1 Auth / roles / permissions (4)

| Migration file | Table(s) |
|----------------|----------|
| `2025_12_18_000000_create_roles_table.php` | `roles` |
| `2025_12_18_000001_create_permissions_table.php` | `permissions` |
| `2025_12_18_000002_create_role_permissions_table.php` | `role_permissions` |
| `2026_05_21_100000_create_auth_role_module_table.php` | `auth_role_module` |

### 3.2 Attendance (1)

| `2025_12_18_170642_create_attendance_logs_table.php` | `attendance_logs` |

### 3.3 Overtime (20)

| Migration file | Table(s) / change |
|----------------|-------------------|
| `2025_12_29_075653_create_educational_levels_table.php` | `educational_levels` |
| `2025_12_29_080158_create_overtime_rates_table.php` | `overtime_rates` |
| `2025_12_29_090845_create_bms_user_table.php` | `bms_user` |
| `2025_12_30_105940_create_overtime_requests_table.php` | `overtime_requests` |
| `2025_12_30_110351_create_overtime_request_days_table.php` | `overtime_request_days` |
| `2025_12_30_110602_create_overtime_request_history_table.php` | `overtime_request_history` |
| `2026_01_29_085323_rename_employee_id_to_pf_number_in_overtime_requests_table.php` | alter `overtime_requests` |
| `2026_01_29_095541_change_performed_by_to_string_in_overtime_request_history_table.php` | alter `overtime_request_history` |
| `2026_01_30_100000_create_overtime_batches_table.php` | `overtime_batches` |
| `2026_01_30_100001_add_batch_id_and_update_status_in_overtime_requests_table.php` | alter `overtime_requests` |
| `2026_01_30_100002_update_overtime_request_history_status_enum.php` | alter `overtime_request_history` |
| `2026_02_10_120000_add_workflow_status_to_overtime_tables.php` | alter overtime tables |
| `2026_02_12_164724_create_overtime_prf_details_table.php` | `overtime_prf_details` |
| `2026_02_22_070300_add_overtime_type_fields_to_overtime_request_days_table.php` | alter `overtime_request_days` |
| `2026_02_26_000000_add_salary_and_tax_fields_to_overtime_requests_table.php` | alter `overtime_requests` |
| `2026_03_03_120000_add_amount_and_prf_url_to_overtime_prf_details_table.php` | alter `overtime_prf_details` |
| `2026_04_09_000001_add_erms_status_to_overtime_batches_table.php` | alter `overtime_batches` |
| `2026_04_17_130000_add_payment_status_to_overtime_batches_table.php` | alter `overtime_batches` |
| `2026_02_23_154735_create_batch_fms_details_table.php` | `batch_fms_details` |
| `2026_02_24_091737_create_prf_votes_table.php` | `prf_votes` |

### 3.4 Bridge employee / HR (20)

| Migration file | Table(s) |
|----------------|----------|
| `2026_01_09_181642_create_bridge_employee_table.php` | `bridge_employee` |
| `2026_01_09_183403_create_bridge_employment_details_table.php` | `bridge_employment_details` |
| `2026_01_09_183858_create_bridge_employment_type_table.php` | `bridge_employment_type` |
| `2026_01_09_184321_create_bridge_employee_status_table.php` | `bridge_employee_status` |
| `2026_01_09_184526_create_bridge_employee_academic_table.php` | `bridge_employee_academic` |
| `2026_01_09_184928_create_scheme_table.php` | `scheme` |
| `2026_01_10_000000_create_bridge_employee_role_table.php` | `bridge_employee_role` |
| `2026_01_13_094857_create_bridge_position_type_table.php` | `bridge_position_type` |
| `2026_01_22_102233_create_bridge_domicile_table.php` | `bridge_domicile` |
| `2026_01_22_103434_create_bridge_district_table.php` | `bridge_district` |
| `2026_01_22_122434_create_banks_table.php` | `bank` |
| `2026_01_30_000000_create_bridge_office_table.php` | `bridge_office` |
| `2026_02_12_153506_create_departments_table.php` | `departments` |
| `2026_02_19_200000_create_bridge_shifts_table.php` | `bridge_shifts` |
| `2026_02_20_093338_remove_role_id_from_bridge_shifts_table.php` | alter `bridge_shifts` |
| `2026_02_20_142333_remove_department_type_from_departments_table.php` | alter `departments` |
| `2026_02_20_143246_remove_updated_at_from_departments_table.php` | alter `departments` |
| `2026_02_20_143743_create_bridge_shift_department_table.php` | `bridge_shift_department` |
| `2026_02_21_224421_add_department_id_to_bridge_employee_table.php` | alter `bridge_employee` |
| `2026_03_18_000001_add_validity_dates_to_bridge_employee_role_table.php` | alter `bridge_employee_role` |

### 3.5 Bridge module / menu (4)

| Migration file | Table(s) |
|----------------|----------|
| `2026_01_15_165107_create_bridge_module_table.php` | `bridge_module` |
| `2026_01_15_170329_create_bridge_module_menu_table.php` | `bridge_module_menu` |
| `2026_01_15_171328_create_bridge_module_role_menu_table.php` | `bridge_module_role_menu` |
| `2026_01_23_103843_create_bridge_module_role_table.php` | `bridge_module_role` |

### 3.6 Other BMS features (4)

| Migration file | Table(s) |
|----------------|----------|
| `2026_01_17_095724_create_referral_request_table.php` | `referral_request` |
| `2026_02_22_065752_create_public_holidays_table.php` | `public_holidays` |
| `2026_02_22_070248_create_special_tasks_table.php` | `special_tasks` |
| `2026_03_02_120000_create_invoices_table.php` | `invoices` |

### 3.7 Payroll (28)

| Migration file | Table(s) / change |
|----------------|-------------------|
| `2026_04_14_000000_create_benefit_type_table.php` | `benefit_type` |
| `2026_04_14_000001_create_employee_benefit_table.php` | `employee_benefit` |
| `2026_04_14_000002_create_deduction_type_table.php` | `deduction_type` |
| `2026_04_14_000003_create_employee_deduction_table.php` | `employee_deduction` |
| `2026_04_14_000004_create_loan_type_table.php` | `loan_type` |
| `2026_04_14_000005_create_employee_loan_table.php` | `employee_loan` |
| `2026_04_14_000006_create_employee_loan_repayment_table.php` | `employee_loan_repayment` |
| `2026_04_14_000007_create_arrears_reasons_table.php` | `arrears_reasons` |
| `2026_04_14_000008_create_employee_arrears_table.php` | `employee_arrears` |
| `2026_04_14_000009_create_tax_brackets_table.php` | `tax_brackets` |
| `2026_04_16_100000_create_employee_payroll_items_table.php` | `employee_payroll_items` |
| `2026_04_16_100005_create_payroll_runs_table.php` | `payroll_runs` |
| `2026_04_16_100006_add_workflow_columns_to_payroll_runs_table.php` | alter `payroll_runs` |
| `2026_04_16_100007_set_payroll_runs_status_default_to_draft.php` | alter `payroll_runs` |
| `2026_04_16_100010_create_payroll_transaction_table.php` | `payroll_transaction` |
| `2026_04_17_100000_add_erms_columns_to_payroll_runs_table.php` | alter `payroll_runs` |
| `2026_04_22_140000_change_deduction_type_created_modified_by_to_string.php` | alter |
| `2026_04_22_150000_change_employee_deduction_created_modified_by_to_string.php` | alter |
| `2026_04_22_160000_change_benefit_type_created_modified_by_to_string.php` | alter |
| `2026_04_22_161000_change_employee_benefit_created_modified_by_to_string.php` | alter |
| `2026_04_22_162000_change_arrears_reasons_created_modified_by_to_string.php` | alter |
| `2026_04_22_163000_change_employee_arrears_created_modified_by_to_string.php` | alter |
| `2026_04_22_164000_change_loan_type_created_modified_by_to_string.php` | alter |
| `2026_04_22_165000_change_employee_loan_created_modified_by_to_string.php` | alter |
| `2026_04_22_166000_change_employee_loan_repayment_created_modified_by_to_string.php` | alter |
| `2026_04_23_180000_create_payroll_transaction_preview_table.php` | `payroll_transaction_preview` |
| `2026_04_25_160200_create_payroll_runs_history_table.php` | `payroll_runs_history` |
| `2026_05_09_090500_add_psssf_contribution_to_payroll_transactions.php` | alter payroll tx tables |
| `2026_05_15_100000_create_payroll_erms_executions_table.php` | `payroll_erms_executions` |

---

## 4. Application code to update

### 4.1 Eloquent models — remove `protected $connection = 'bcmis2'` (48 files)

**`app/Models/Bms/`**

- `AttendanceLog.php`
- `Bank.php`
- `BatchFmsDetail.php`
- `BridgeEmployee.php`
- `BridgeEmployeeRole.php`
- `BridgeEmployeeStatus.php`
- `BridgeEmploymentType.php`
- `BridgeModule.php`
- `BridgeModuleRole.php`
- `BridgeOffice.php`
- `BridgeShift.php`
- `BridgeShiftDepartment.php`
- `Department.php`
- `Domicile.php`
- `District.php`
- `EducationalLevel.php`
- `Invoice.php`
- `OvertimeBatch.php`
- `OvertimePrfDetail.php`
- `OvertimeRates.php`
- `OvertimeRequest.php` *(also update inline `DB::connection('bcmis2')` query)*
- `OvertimeRequestDay.php`
- `OvertimeRequestHistory.php`
- `Permission.php`
- `PrfVote.php`
- `PublicHoliday.php`
- `ReferralRequest.php`
- `Role.php`
- `RolePermission.php`
- `Scheme.php`
- `SpecialTask.php`

**`app/Models/Bms/Payroll/`**

- `ArrearsReason.php`
- `BenefitType.php`
- `DeductionType.php`
- `EmployeeArrears.php`
- `EmployeeBenefit.php`
- `EmployeeDeduction.php`
- `EmployeeLoan.php`
- `EmployeeLoanRepayment.php`
- `EmployeePayrollItem.php`
- `LoanType.php`
- `PayrollErmsExecution.php`
- `PayrollRun.php`
- `PayrollRunHistory.php`
- `PayrollTransaction.php`
- `PayrollTransactionPreview.php`
- `TaxBracket.php`

**Other models**

- `app/Models/AuthRoleModule.php`
- `app/Models/AuthUser.php` — review/remove bcmis2 workaround comment after switch

### 4.2 Controllers — `DB::connection('bcmis2')` (12 files)

| File |
|------|
| `app/Http/Controllers/Bms/BridgeModuleController.php` |
| `app/Http/Controllers/Bms/BridgeModuleMenuController.php` |
| `app/Http/Controllers/Bms/BridgeModuleRoleController.php` |
| `app/Http/Controllers/Bms/BridgeModuleRoleMenuController.php` |
| `app/Http/Controllers/Bms/PublicHolidayController.php` |
| `app/Http/Controllers/Bms/SpecialTaskController.php` |
| `app/Http/Controllers/Bms/DepartmentController.php` |
| `app/Http/Controllers/Bms/BridgeShiftController.php` |
| `app/Http/Controllers/Bms/Payroll/PayrollController.php` |
| `app/Http/Controllers/Bms/BridgeEmployeeController.php` |
| `app/Http/Controllers/Bms/BridgeShiftDepartmentController.php` |
| `app/Http/Controllers/Erms/ErmsPayableCallbackController.php` |

### 4.3 Controllers — `DB::table('bcmis2.<table>')` schema prefix (19 files)

Change `bcmis2.table_name` → `table_name`:

| File |
|------|
| `app/Http/Controllers/Bms/Payroll/LoanTypeController.php` |
| `app/Http/Controllers/Bms/RoleController.php` |
| `app/Http/Controllers/Bms/Payroll/EmployeeDeductionController.php` |
| `app/Http/Controllers/Bms/Payroll/BenefitTypeController.php` |
| `app/Http/Controllers/Bms/Payroll/DeductionTypeController.php` |
| `app/Http/Controllers/Bms/Payroll/EmployeeBenefitController.php` |
| `app/Http/Controllers/Bms/Payroll/EmployeeArrearsController.php` |
| `app/Http/Controllers/Bms/Payroll/ArrearsReasonController.php` |
| `app/Http/Controllers/Bms/BankController.php` |
| `app/Http/Controllers/Bms/DistrictController.php` |
| `app/Http/Controllers/Bms/RegionController.php` |
| `app/Http/Controllers/Bms/SchemeController.php` |
| `app/Http/Controllers/Bms/EducationalLevelController.php` |
| `app/Http/Controllers/Bms/BridgeEmploymentTypeController.php` |
| `app/Http/Controllers/Bms/AttendanceLogController.php` |
| `app/Http/Controllers/Bms/OvertimeRatesController.php` |
| `app/Http/Controllers/Bms/PermissionController.php` |
| `app/Http/Controllers/Bms/RolePermissionController.php` |
| `app/Http/Controllers/Bms/PublicHolidayController.php` |

### 4.4 Validation rules — `exists:bcmis2.*` → `exists:*` (8 files)

| File |
|------|
| `app/Http/Controllers/Bms/BridgeModuleMenuController.php` |
| `app/Http/Controllers/Bms/OvertimeRatesController.php` |
| `app/Http/Controllers/Bms/BridgeModuleRoleMenuController.php` |
| `app/Http/Controllers/Bms/BridgeEmployeeController.php` |
| `app/Http/Controllers/Bms/DistrictController.php` |
| `app/Http/Controllers/Bms/RolePermissionController.php` |
| `app/Http/Controllers/Bms/BridgeShiftDepartmentController.php` |
| `app/Http/Controllers/Bms/BridgeModuleRoleController.php` |

### 4.5 Services (22 files)

| Area | Files |
|------|-------|
| Payroll | `PayrollProcessor.php`, `PayrollPreviewProcessor.php`, `PayrollWorkflowService.php`, `PayrollDocumentService.php`, `PayrollItemWriter.php`, `PayrollTransactionRowBuilder.php` |
| Overtime | `OvertimePrfBudgetService.php`, `OvertimeWorkflowService.php`, `OvertimeInvoiceService.php` |
| ERMS mappers | `OvertimeBatchPayableMapper.php`, `OvertimeRequestPayeeMapper.php`, `PayrollRunPayableMapper.php`, `PayrollRunMiscellaneousMapper.php`, `PayrollNetPayMapper.php`, `PayrollTransactionPayeeMapper.php` |
| ERMS payroll | `DeductionAmountResolverService.php`, `PayrollDeductionPayableConfig.php` |
| Other | `InvoiceService.php`, `ReferralApprovalService.php`, `AttendanceLogService.php`, `AttendanceViolationService.php`, `AuthRoleModuleService.php` |

### 4.6 Console commands (1 file)

- `app/Console/Commands/SyncBridgeEmployeeRoleValidity.php`

### 4.7 Seeders (3 files)

- `database/seeders/TaxBracketsSeeder.php`
- `database/seeders/BankSeeder.php`
- `database/seeders/BridgeOfficeSeeder.php`

### 4.8 Configuration & environment

| Item | Action |
|------|--------|
| `config/database.php` | Remove `bcmis2` connection block after all references are gone |
| `.env` / deployment secrets | Remove `DB2_HOST`, `DB2_DATABASE`, `DB2_USERNAME`, `DB2_PASSWORD`, `DB2_PORT`, `DB2_SOCKET`, `DB2_CONNECTION` |

---

## 5. Execution order

```
1. Run discovery queries (Section 2)
2. Backup both databases
3. Migrate data to default schema if needed (Option B)
4. Update 88 migration files
5. Remove $connection = 'bcmis2' from 48 models
6. Update controllers (connection, schema prefix, validation)
7. Update services, command, seeders
8. Review AuthUser cross-connection workaround
9. Remove bcmis2 from config/database.php and .env
10. Regression test all BMS modules
```

---

## 6. Testing checklist

- [ ] `php artisan migrate:status` — no unexpected pending migrations
- [ ] Bridge employee CRUD + role assignment
- [ ] Overtime request workflow (submit → validate → batch → payment)
- [ ] Payroll run create → preview → process → ERMS submission
- [ ] Attendance log ingestion
- [ ] Module / menu / role permission assignment
- [ ] Invoice generation
- [ ] `php artisan sync:bridge-employee-role-validity` (or equivalent command name)
- [ ] Seeders: `TaxBracketsSeeder`, `BankSeeder`, `BridgeOfficeSeeder`

---

## 7. Risks

| Risk | Mitigation |
|------|------------|
| Cross-database relationships (`AuthUser` on default ↔ BMS on bcmis2) | All models on default after switch; retest relationships |
| Table name collisions on default schema | Run conflict check (Section 2.3) before cutover |
| Qualified names `DB::table('bcmis2.x')` break without bcmis2 schema | Replace with unqualified table names |
| Tables only on bcmis2 DB | Data migration required; editing migrations alone is not enough |
| Production downtime | Backup + staged rollout on dev/staging first |

---

## 8. Summary counts

| Category | Count |
|----------|-------|
| Migration files | **88** |
| Eloquent models | **48** |
| HTTP controllers | **~30** |
| Services | **22** |
| Seeders | **3** |
| Console commands | **1** |
| Config files | **1** |

---

*Document created: 2026-06-07. Target: default schema only — no bcmis2.*
