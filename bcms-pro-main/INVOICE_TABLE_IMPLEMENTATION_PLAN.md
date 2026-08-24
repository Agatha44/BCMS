# Invoice Table Implementation Plan

## Overview
Create an Invoice table for overtime batches. Invoices are created manually via button click, grouped by bank, with one invoice number per bank per batch shared by all employees in that bank.

---

## Requirements

- ✅ Manual creation via button click (requires PRF status = `'prf_created'`)
- ✅ One invoice per employee per batch
- ✅ Invoice number: `BMS/BANK_NAME/DDMMMYYYY` (e.g., `BMS/CRDB/02JAN2026`)
- ✅ Same invoice number for all employees in same bank + batch
- ✅ Response grouped by bank with list of individuals
- ✅ Amount from employee's `overtime_request.total_amount`
- ✅ Customer bank account number from `bridge_employee.account_no`
- ✅ Description: `BMS Payments for OT [Month] [Year]`

---

## 1. Database Structure

### Table: `invoices`
**Connection**: `bcmis2`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `invoice_number` | VARCHAR(100) | Format: `BMS/BANK_NAME/DDMMMYYYY` (shared by bank+batch) |
| `batch_number` | VARCHAR(50) | FK to `overtime_batches.batch_number` |
| `pf_number` | VARCHAR(50) | Employee PF number |
| `employee_name` | VARCHAR(200) | Employee full name |
| `customer_bank_account_no` | VARCHAR(50) | Customer bank account number (from `bridge_employee.account_no`) |
| `amount` | DECIMAL(15,2) | From employee's `overtime_request.total_amount` |
| `description` | VARCHAR(500) | `BMS Payments for OT [Month] [Year]` |
| `accts_pay_code_combination` | VARCHAR(100) | Provided constant |
| `created_by` | VARCHAR(50) | PF number of creator |
| `expense_accounts` | VARCHAR(100) | Provided constant |
| `vendor` | VARCHAR(100) | Provided constant |
| `vendor_site_id` | VARCHAR(100) | Provided constant |
| `terms_id` | VARCHAR(100) | Provided constant |
| `bank_id` | BIGINT UNSIGNED | FK to `bank.bank_id` |
| `created_at`, `updated_at` | TIMESTAMP | Timestamps |

### Indexes & Constraints
- Primary key: `id`
- Unique: `(batch_number, pf_number)` - prevents duplicate per employee per batch
- Indexes: `invoice_number`, `batch_number`, `pf_number`, `bank_id`, `created_at`
- Foreign keys: `batch_number` → `overtime_batches`, `bank_id` → `bank`

**Note**: `invoice_number` is NOT unique - multiple employees in same bank+batch share it.

---

## 2. Migration

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_create_invoices_table.php`

```php
Schema::connection('bcmis2')->create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number', 100)->index();
    $table->string('batch_number', 50)->index();
    $table->string('pf_number', 50)->index();
    $table->string('employee_name', 200);
    $table->string('customer_bank_account_no', 50)->nullable();
    $table->decimal('amount', 15, 2);
    $table->string('description', 500);
    $table->string('accts_pay_code_combination', 100)->nullable();
    $table->string('created_by', 50);
    $table->string('expense_accounts', 100);
    $table->string('vendor', 100);
    $table->string('vendor_site_id', 100);
    $table->string('terms_id', 100);
    $table->unsignedBigInteger('bank_id')->index();
    $table->timestamps();

    $table->foreign('batch_number')->references('batch_number')->on('overtime_batches');
    $table->foreign('bank_id')->references('bank_id')->on('bank');
    $table->unique(['batch_number', 'pf_number'], 'unique_batch_employee');
});
```

---

## 3. Model

**File**: `app/Models/Bms/Invoice.php`

```php
<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $connection = 'bcmis2';
    protected $table = 'invoices';
    
    protected $fillable = [
        'invoice_number', 'batch_number', 'pf_number', 'employee_name',
        'customer_bank_account_no', 'amount', 'description', 'accts_pay_code_combination', 'created_by',
        'expense_accounts', 'vendor', 'vendor_site_id', 'terms_id',
        'bank_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bank_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OvertimeBatch::class, 'batch_number', 'batch_number');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id', 'bank_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AuthUser::class, 'created_by', 'id');
    }

    public static function existsForBatchAndEmployee(string $batchNumber, string $pfNumber): bool
    {
        return self::where('batch_number', $batchNumber)
            ->where('pf_number', $pfNumber)
            ->exists();
    }
}
```

---

## 4. Service

**File**: `app/Services/InvoiceService.php`

```php
<?php

namespace App\Services;

use App\Models\Bms\{Invoice, OvertimeBatch, OvertimeRequest, OvertimePrfDetail, Bank};
use Illuminate\Support\Facades\{DB, Log};

class InvoiceService
{
    /**
     * Create invoices for all employees in batch
     * Groups by bank, generates one invoice number per bank+batch
     */
    public function createInvoicesForBatch(string $batchNumber, int $createdByUserId = null): array
    {
        $createdInvoices = [];

        // Validate PRF exists
        $prfDetail = OvertimePrfDetail::where('batch_number', $batchNumber)
            ->where('status', 'prf_created')
            ->first();

        if (!$prfDetail) {
            Log::warning('PRF not created for batch', ['batch_number' => $batchNumber]);
            return $createdInvoices;
        }

        // Get batch and requests
        $batch = OvertimeBatch::where('batch_number', $batchNumber)->first();
        if (!$batch) return $createdInvoices;

        $overtimeRequests = OvertimeRequest::where('batch_id', $batch->id)->get();
        if ($overtimeRequests->isEmpty()) return $createdInvoices;

        // Get constants
        $expenseAccounts = config('invoice.expense_accounts');
        $vendor = config('invoice.vendor');
        $vendorSiteId = config('invoice.vendor_site_id');
        $termsId = config('invoice.terms_id');
        $acctsPayCodeCombination = config('invoice.accts_pay_code_combination');
        $createdByPfNumber = $this->getPfNumberFromUserId($createdByUserId ?? auth()->id());

        // Group requests by bank_id
        $requestsByBank = [];
        foreach ($overtimeRequests as $request) {
            $employee = DB::connection('bcmis2')
                ->table('bridge_employee')
                ->where('pfno', $request->pf_number)
                ->first();

            if (!$employee || !$employee->bank_id) continue;

            $bankId = $employee->bank_id;
            if (!isset($requestsByBank[$bankId])) {
                $requestsByBank[$bankId] = [];
            }
            $requestsByBank[$bankId][] = ['request' => $request, 'employee' => $employee];
        }

        // Process each bank group
        foreach ($requestsByBank as $bankId => $bankRequests) {
            $bank = Bank::find($bankId);
            if (!$bank) continue;

            $firstRequest = $bankRequests[0]['request'];
            $invoiceDate = $firstRequest->month ?? now();
            $description = $this->generateDescription($invoiceDate);
            $invoiceNumber = $this->generateInvoiceNumber($bank, $batchNumber, $invoiceDate);

            // Create invoices for all employees in this bank with same invoice number
            foreach ($bankRequests as $data) {
                $request = $data['request'];
                $employee = $data['employee'];
                $pfNumber = $request->pf_number;

                if (Invoice::existsForBatchAndEmployee($batchNumber, $pfNumber)) {
                    continue;
                }

                try {
                    DB::connection('bcmis2')->beginTransaction();

                    $invoice = Invoice::create([
                        'invoice_number' => $invoiceNumber, // Same for all in bank+batch
                        'batch_number' => $batchNumber,
                        'pf_number' => $pfNumber,
                        'employee_name' => trim(($employee->fname ?? '') . ' ' . ($employee->sname ?? '')),
                        'customer_bank_account_no' => $employee->account_no,
                        'amount' => $request->total_amount,
                        'description' => $description,
                        'accts_pay_code_combination' => $acctsPayCodeCombination,
                        'created_by' => $createdByPfNumber,
                        'expense_accounts' => $expenseAccounts,
                        'vendor' => $vendor,
                        'vendor_site_id' => $vendorSiteId,
                        'terms_id' => $termsId,
                        'bank_id' => $bankId,
                    ]);

                    DB::connection('bcmis2')->commit();
                    $createdInvoices[] = $invoice;

                } catch (\Exception $e) {
                    DB::connection('bcmis2')->rollBack();
                    Log::error('Failed to create invoice', [
                        'batch_number' => $batchNumber,
                        'pf_number' => $pfNumber,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $createdInvoices;
    }

    private function generateDescription($date = null): string
    {
        $dateObj = $date ? (is_string($date) ? new \DateTime($date) : $date) : now();
        return "BMS Payments for OT " . $dateObj->format('M Y');
    }

    private function generateInvoiceNumber(Bank $bank, string $batchNumber, $date = null): string
    {
        $bankName = preg_replace('/[^A-Z0-9]/', '', strtoupper($bank->short_name));
        $dateFormatted = ($date ? (is_string($date) ? new \DateTime($date) : $date) : now())->format('dMY');
        $baseInvoiceNumber = "BMS/{$bankName}/" . strtoupper($dateFormatted);

        // Check if invoice number already exists for this batch+bank
        $existing = Invoice::where('batch_number', $batchNumber)
            ->where('bank_id', $bank->bank_id)
            ->first();

        return $existing ? $existing->invoice_number : $baseInvoiceNumber;
    }

    private function getPfNumberFromUserId(int $userId): ?string
    {
        $user = \App\Models\AuthUser::find($userId);
        return $user->pf_number ?? null;
    }
}
```

---

## 5. Controller

**File**: `app/Http/Controllers/Bms/InvoiceController.php`

```php
<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Models\Bms\{Invoice, OvertimePrfDetail};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{Validator, Log};

class InvoiceController extends Controller
{
    /**
     * Create invoices for batch and return grouped by bank
     * POST /api/bms/invoices/create
     */
    public function createInvoices(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'batch_number' => 'required|string|exists:overtime_batches,batch_number',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $batchNumber = $request->batch_number;

            // Validate PRF
            $prfDetail = OvertimePrfDetail::where('batch_number', $batchNumber)
                ->where('status', 'prf_created')
                ->first();

            if (!$prfDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'PRF must be created first for this batch'
                ], 400);
            }

            // Create invoices
            $invoiceService = new InvoiceService();
            $createdInvoices = $invoiceService->createInvoicesForBatch($batchNumber, auth()->id());

            if (empty($createdInvoices)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No invoices created. All may already exist.'
                ], 400);
            }

            // Group by bank
            $groupedInvoices = collect($createdInvoices)->groupBy('bank_id');
            $result = [];

            foreach ($groupedInvoices as $bankId => $bankInvoices) {
                $bank = $bankInvoices->first()->bank;
                $firstInvoice = $bankInvoices->first();

                $result[] = [
                    'bank_id' => $bank->bank_id,
                    'bank_name' => $bank->bank_name,
                    'short_name' => $bank->short_name,
                    'invoice_number' => $firstInvoice->invoice_number, // At bank level
                    'description' => $firstInvoice->description,
                    'total_individuals' => $bankInvoices->count(),
                    'total_amount' => $bankInvoices->sum('amount'),
                    'individuals' => $bankInvoices->map(function($invoice) {
                        return [
                            'id' => $invoice->id,
                            'invoice_id' => $invoice->id,
                            'batch_number' => $invoice->batch_number,
                            'pf_number' => $invoice->pf_number,
                            'employee_name' => $invoice->employee_name,
                            'customer_bank_account_no' => $invoice->customer_bank_account_no,
                            'amount' => $invoice->amount,
                            'accts_pay_code_combination' => $invoice->accts_pay_code_combination,
                            'created_by' => $invoice->created_by,
                            'expense_accounts' => $invoice->expense_accounts,
                            'vendor' => $invoice->vendor,
                            'vendor_site_id' => $invoice->vendor_site_id,
                            'terms_id' => $invoice->terms_id,
                            'created_at' => $invoice->created_at,
                        ];
                    })->values(),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoices created successfully',
                'data' => [
                    'batch_number' => $batchNumber,
                    'total_invoices_created' => count($createdInvoices),
                    'invoices_by_bank' => $result
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create invoices', [
                'batch_number' => $request->batch_number ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoices grouped by bank
     * GET /api/bms/invoices/by-bank
     */
    public function getInvoiceByBank(Request $request): JsonResponse
    {
        try {
            $query = Invoice::with(['bank', 'creator']);

            if ($request->has('bank_id')) {
                $query->where('bank_id', $request->bank_id);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }

            $invoices = $query->get()->groupBy('bank_id');
            $result = [];

            foreach ($invoices as $bankId => $bankInvoices) {
                $bank = $bankInvoices->first()->bank;
                $firstInvoice = $bankInvoices->first();

                $result[] = [
                    'bank_id' => $bank->bank_id,
                    'bank_name' => $bank->bank_name,
                    'short_name' => $bank->short_name,
                    'invoice_number' => $firstInvoice->invoice_number,
                    'description' => $firstInvoice->description,
                    'total_individuals' => $bankInvoices->count(),
                    'total_amount' => $bankInvoices->sum('amount'),
                    'individuals' => $bankInvoices->map(function($invoice) {
                        return [
                            'id' => $invoice->id,
                            'invoice_id' => $invoice->id,
                            'batch_number' => $invoice->batch_number,
                            'pf_number' => $invoice->pf_number,
                            'employee_name' => $invoice->employee_name,
                            'amount' => $invoice->amount,
                            'created_at' => $invoice->created_at,
                        ];
                    })->values(),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoices retrieved successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoices: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

---

## 6. Routes

**File**: `routes/api.php`

```php
Route::prefix('bms')->middleware(['auth:sanctum'])->group(function () {
    Route::post('invoices/create', [InvoiceController::class, 'createInvoices']);
    Route::get('invoices/by-bank', [InvoiceController::class, 'getByBank']);
    Route::get('invoices/batch/{batchNumber}', [InvoiceController::class, 'getByBatch']);
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);
});
```

---

## 7. Configuration

**File**: `config/invoice.php`

```php
<?php

return [
    'expense_accounts' => env('INVOICE_EXPENSE_ACCOUNTS', 'EXP001'),
    'vendor' => env('INVOICE_VENDOR', 'VENDOR001'),
    'vendor_site_id' => env('INVOICE_VENDOR_SITE_ID', 'SITE001'),
    'terms_id' => env('INVOICE_TERMS_ID', 'TERMS001'),
    'accts_pay_code_combination' => env('INVOICE_ACCTS_PAY_CODE_COMBINATION', 'ACC001'),
];
```

**.env**:
```
INVOICE_EXPENSE_ACCOUNTS=EXP001
INVOICE_VENDOR=VENDOR001
INVOICE_VENDOR_SITE_ID=SITE001
INVOICE_TERMS_ID=TERMS001
INVOICE_ACCTS_PAY_CODE_COMBINATION=ACC001
```

---

## 8. API Examples

### Create Invoices
**POST** `/api/bms/invoices/create`
```json
{
    "batch_number": "OTB-20260102-0001"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Invoices created successfully",
    "data": {
        "batch_number": "OTB-20260102-0001",
        "total_invoices_created": 3,
        "invoices_by_bank": [
            {
                "bank_id": 1,
                "bank_name": "CRDB Bank",
                "short_name": "CRDB",
                "invoice_number": "BMS/CRDB/02JAN2026",
                "description": "BMS Payments for OT Feb 2026",
                "total_individuals": 2,
                "total_amount": 90000.00,
                "individuals": [
                    {
                        "id": 1,
                        "pf_number": "PF001",
                        "employee_name": "John Doe",
                        "customer_bank_account_no": "1234567890",
                        "amount": 50000.00
                    },
                    {
                        "id": 3,
                        "pf_number": "PF003",
                        "employee_name": "Jane Smith",
                        "customer_bank_account_no": "0987654321",
                        "amount": 40000.00
                    }
                ]
            }
        ]
    }
}
```

---

## 9. Implementation Checklist

- [ ] Create migration
- [ ] Create Invoice model
- [ ] Create InvoiceService
- [ ] Create InvoiceController
- [ ] Add routes
- [ ] Create config file
- [ ] Test invoice creation
- [ ] Test PRF validation
- [ ] Test duplicate prevention
- [ ] Test grouping by bank

---

## 10. Key Points

- **One invoice per employee per batch**
- **One invoice number per bank+batch** (shared by all employees in that bank)
- **Invoice number format**: `BMS/BANK_NAME/DDMMMYYYY`
- **Response grouped by bank** with `individuals` array
- **Invoice number at bank level**, not in individual records
- **PRF must be created** before invoices can be created
- **Duplicate prevention**: Unique constraint on `(batch_number, pf_number)`
