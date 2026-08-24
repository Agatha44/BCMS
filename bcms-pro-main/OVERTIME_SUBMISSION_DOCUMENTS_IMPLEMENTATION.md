# Overtime Submission Documents — Implementation Document

## Overview

Implement a **period-scoped document registry** for overtime eOffice submission. Users upload PDF documents (starting with **approval_memo**) with a user-defined validity period. Before a batch is submitted to eOffice, the system resolves the required document by **`document_type`**, validates it against **`overtime_batches.created_at`**, and blocks submission if the document is missing or expired.

The **invoice** attachment remains **generated at submit time** (`MAIN`). Registry documents are loaded from storage (`ATTACHMENT`).

---

## Business Rules (Frozen)

| Rule | Decision |
|------|----------|
| Canonical lookup key | **`document_type`** (e.g. `approval_memo`) |
| Display label | **`document_name`** — optional on upload |
| `period_start` | **User-entered** — system must not default or normalize |
| `period_end` | **User-entered** |
| Batch reference date | `date(overtime_batches.created_at)` |
| Multi-month batches | **Allowed** (reference is create date, not request month) |
| Document match | `period_start <= created_at.date <= period_end` |
| Expiry | `period_end < today` → invalid for new eOffice submits |
| Overlap policy | **Reject** upload if active doc of same `document_type` overlaps |
| Supersede | **No** — existing rows are never auto-replaced |
| Multiple matches on submit | **Newest upload wins** (`uploaded_at DESC`, `id DESC`) — safety net only |
| Invoice | Generated on submit → `documentFlag: MAIN` |
| Approval memo (v1) | Loaded from DB → `documentFlag: ATTACHMENT` |
| Permission | Overtime reviewer role (same as batch submit) |

---

## Architecture

```
┌─────────────────┐     POST upload      ┌──────────────────────────────────┐
│  Frontend / API │ ──────────────────►  │ OvertimeSubmissionDocumentService │
└─────────────────┘                      └──────────────────────────────────┘
                                                    │                │
                                         validate + store file       │
                                                    ▼                ▼
                                         ┌─────────────────────────────────────┐
                                         │ overtime_submission_documents (DB)  │
                                         │ + storage/overtime/submission-docs  │
                                         └─────────────────────────────────────┘

┌─────────────────┐   POST batch/submit   ┌──────────────────────────────────┐
│ OvertimeController│ ─────────────────► │ 1. assessBatchReadiness()         │
│ saveAndSubmitBatch│                     │ 2. resolve by document_type       │
└─────────────────┘                     │ 3. load PDF base64                 │
                                         │ 4. generate invoice                │
                                         │ 5. postEofficeOffice()             │
                                         └──────────────────────────────────┘
```

---

## 1. Database

### Table: `overtime_submission_documents`

**Connection:** `bcmis2`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT UNSIGNED | Primary key |
| `document_type` | VARCHAR(50) | Canonical key — `approval_memo`, etc. |
| `document_name` | VARCHAR(255) NULL | Optional display label |
| `period_start` | DATE | User-entered start |
| `period_end` | DATE | User-entered end |
| `file_path` | VARCHAR(500) | Path relative to configured disk |
| `file_name` | VARCHAR(255) | Original upload filename |
| `mime_type` | VARCHAR(100) | `application/pdf` |
| `status` | ENUM | `active`, `expired` |
| `uploaded_by` | BIGINT UNSIGNED NULL | `auth_user.id` |
| `uploaded_at` | TIMESTAMP | Used for newest-wins resolution |
| `created_at`, `updated_at` | TIMESTAMP | Laravel timestamps |

### Indexes

- `(document_type, status, period_start, period_end)`
- `(document_type, status, uploaded_at)`
- `uploaded_by`

### Migration file

`database/migrations/2026_06_18_000000_create_overtime_submission_documents_table.php`

---

## 2. Configuration

### File: `config/overtime.php`

```php
<?php

return [
    'submission_documents' => [
        'disk' => 'local',
        'directory' => 'overtime/submission-documents',
        'max_file_size_kb' => 10240,
        'allowed_mimes' => ['application/pdf'],
        'allowed_extensions' => ['pdf'],

        'types' => [
            'approval_memo',
        ],

        'overlap_policy' => 'reject',
        'selection_rule' => 'newest_upload_wins',
    ],

    'eoffice_attachments' => [
        [
            'document_type'    => 'approval_memo',
            'document_name'    => 'Approval Memo',
            'attachment_flag'  => 'ATTACHMENT',
            'file_name_prefix' => 'Approval_Memo_',
            'required'         => true,
        ],
    ],
];
```

**Period end calculation (preset):**

```text
period_end = period_start + N months - 1 day

Example: period_start = 2026-01-15, preset = 3_months
         period_end   = 2026-04-14
```

---

## 3. Domain Model

### File: `app/Models/Bms/OvertimeSubmissionDocument.php`

- Connection: `bcmis2`
- Table: `overtime_submission_documents`
- Constants: `STATUS_ACTIVE`, `STATUS_EXPIRED`, `TYPE_APPROVAL_MEMO`
- Casts: `period_start`, `period_end` → `date`; `uploaded_at` → `datetime`
- Scopes:
  - `scopeActive($query)`
  - `scopeOfType($query, string $type)`
  - `scopeCoveringDate($query, $date)` — `period_start <= $date AND period_end >= $date`
  - `scopeNotExpired($query)` — `period_end >= today()`
- Relation: `uploadedBy()` → `AuthUser`

---

## 4. Service Layer

### File: `app/Services/Overtime/OvertimeSubmissionDocumentService.php`

#### 4.1 `getDocumentPeriod(array $input): array`

Validates user-entered `period_start` and `period_end`. No preset or auto-calculated end date.

#### 4.2 `buildPeriodLabel(Carbon $start, Carbon $end): string`

Computed at API response time in `serializeDocument()` — not stored in the database.

#### 4.3 `findOverlappingActive(string $documentType, Carbon $start, Carbon $end): Collection`

```sql
WHERE document_type = ?
  AND status = 'active'
  AND period_start <= ?
  AND period_end >= ?
```

If any row exists → upload rejected (422).

#### 4.4 `uploadDocument(array $input, UploadedFile $file, int $userId): OvertimeSubmissionDocument`

1. Validate `document_type` against allowlist.
2. Parse `period_start` as user submitted (no normalization).
3. Resolve `period_end` from preset or custom input.
4. Reject if overlapping active document exists (same `document_type`).
5. Store PDF under `{directory}/{document_type}/{uuid}.pdf`.
6. Insert row: `status = active`, `uploaded_at = now()`.

#### 4.5 `resolveForBatch(OvertimeBatch $batch, string $documentType): ?OvertimeSubmissionDocument`

```php
$referenceDate = $batch->created_at->toDateString();

return OvertimeSubmissionDocument::query()
    ->ofType($documentType)
    ->active()
    ->coveringDate($referenceDate)
    ->notExpired()
    ->orderByDesc('uploaded_at')
    ->orderByDesc('id')
    ->first();
```

#### 4.6 `assessBatchReadiness(OvertimeBatch $batch): array`

Loop `config('overtime.eoffice_attachments')` where `required = true`. For each `document_type`, call `resolveForBatch()` and build:

```json
{
  "batch_reference_date": "2026-03-15",
  "source": "created_at",
  "required_documents": [
    {
      "document_type": "approval_memo",
      "document_name": "Approval Memo",
      "required": true,
      "status": "valid|missing|expired",
      "matched_document_id": 18,
      "period_start": "2026-01-01",
      "period_end": "2026-06-30",
      "uploaded_at": "2026-03-10T14:20:00"
    }
  ],
  "can_submit_to_eoffice": true,
  "blocking_reasons": []
}
```

**Status logic:**

| Condition | `status` |
|-----------|----------|
| Active doc covers `created_at` and `period_end >= today` | `valid` |
| Doc covers date but `period_end < today` | `expired` |
| No covering doc | `missing` |

#### 4.7 `readAsBase64(OvertimeSubmissionDocument $doc): string`

Read file from disk, return `base64_encode($binary)`. Throw if file missing.

#### 4.8 `listOvertimeSubmissionDocuments(array $filters): array`

Filters: `document_type`, `document_name`, `status`, pagination.

---

## 5. API Endpoints

All routes under `auth:sanctum`. Require `isOvertimeReviewer()`.

### 5.1 Upload document

```
POST /api/overtime/submission-documents
Content-Type: multipart/form-data
```

| Field | Rules |
|-------|-------|
| `document_type` | required, `in:approval_memo` (from config) |
| `document_name` | optional, string, max 255 |
| `period_start` | required, date |
| `period_end` | required, date, after_or_equal:period_start |
| `file` | required, file, mimes:pdf, max:10240 |

**Note:** `period_label` in API responses is computed from `period_start` and `period_end` — not stored.

**Success (201):**

```json
{
  "success": true,
  "data": {
    "id": 15,
    "document_type": "approval_memo",
    "document_name": "Board Approval Memo Q1",
    "period_start": "2026-01-15",
    "period_end": "2026-04-14",
    "period_label": "15 Jan 2026 – 14 Apr 2026",
    "file_name": "approval_memo.pdf",
    "status": "active",
    "uploaded_at": "2026-06-18T10:30:00"
  }
}
```

**Overlap error (422):**

```json
{
  "success": false,
  "message": "An active document of this type already covers part of the requested period.",
  "errors": {
    "period_start": ["Overlaps with document #12 (2026-01-01 to 2026-06-30)."]
  }
}
```

### 5.2 List documents

```
GET /api/overtime/submission-documents?document_type=approval_memo&status=active&page=1
```

Optional filter: `document_name` (partial match).

### 5.3 Show document metadata

```
GET /api/overtime/submission-documents/{id}
```

Does not return file bytes.

### 5.4 Download document

```
GET /api/overtime/submission-documents/{id}/download
```

Returns PDF inline or as download.

### 5.5 Batch submission readiness

```
GET /api/overtime/batches/{batchId}/submission-readiness
```

Returns `assessBatchReadiness()` payload.

### 5.6 Allowed document types (optional helper)

```
GET /api/overtime/submission-documents/types
```

Returns `config('overtime.submission_documents.types')` and preset options for the upload form.

---

## 6. eOffice Submit Integration

### File: `app/Http/Controllers/Bms/OvertimeController.php`

#### Method: `saveAndSubmitBatch`

**New steps before OAuth token request:**

1. Load batch with `overtimeRequests`.
2. Ensure `status === 'draft'` and batch is not empty.
3. Call `assessBatchReadiness($batch)`.
4. If `can_submit_to_eoffice === false` → return `422` with `blocking_reasons`.

**Attachment building (replace current invoice-only block ~line 574):**

```php
$attachments = [];

// MAIN — invoice (generated, required)
$pdfBase64 = $this->overtimeInvoiceService->generateInvoiceDocument(
    $batch->batch_number,
    'overtime'
);
if (!$pdfBase64) {
    DB::rollBack();
    return $this->sendError('Failed to generate invoice document.', [], 0, 422);
}

$attachments[] = [
    'attachmentTitle' => 'Invoice_' . $batch->batch_number . '.pdf',
    'attachmentData'  => $pdfBase64,
    'documentFlag'    => 'MAIN',
];

// ATTACHMENT — required registry documents
foreach (config('overtime.eoffice_attachments') as $spec) {
    if (!($spec['required'] ?? false)) {
        continue;
    }

    $doc = $this->overtimeSubmissionDocumentService->resolveForBatch(
        $batch,
        $spec['document_type']
    );

    if ($doc === null) {
        DB::rollBack();
        return $this->sendError(
            'No valid document found for type: ' . $spec['document_type'],
            ['blocking_reasons' => $readiness['blocking_reasons'] ?? []],
            0,
            422
        );
    }

    $prefix = $spec['file_name_prefix'] ?? ucfirst($spec['document_type']) . '_';
    $attachments[] = [
        'attachmentTitle' => $prefix . $batch->batch_number . '.pdf',
        'attachmentData'  => $this->overtimeSubmissionDocumentService->readAsBase64($doc),
        'documentFlag'    => $spec['attachment_flag'] ?? 'ATTACHMENT',
    ];
}

// Continue to postEofficeOffice(...)
```

Existing eOffice payload shape in `EOfficeTrait::postEofficeOffice()` is unchanged.

---

## 7. Batch API Enrichment

### File: `app/Services/Overtime/OvertimeBatchService.php`

Inject `OvertimeSubmissionDocumentService`.

Add to `getBatch()` response:

```php
'submission_readiness' => $this->overtimeSubmissionDocumentService->assessBatchReadiness($batch),
```

Optionally add `can_submit_to_eoffice` boolean to `listBatches()` summary rows.

---

## 8. Controller Wiring

### `OvertimeController` constructor

Add:

```php
private OvertimeSubmissionDocumentService $overtimeSubmissionDocumentService;
```

### New controller methods

| Method | Delegates to |
|--------|----------------|
| `uploadSubmissionDocument` | `uploadDocument()` |
| `listSubmissionDocuments` | `listOvertimeSubmissionDocuments()` |
| `showSubmissionDocument` | find + metadata |
| `downloadSubmissionDocument` | file response |
| `getBatchSubmissionReadiness` | `assessBatchReadiness()` |
| `listSubmissionDocumentTypes` | config helper |

---

## 9. Routes

**File:** `routes/api.php` (inside overtime reviewer middleware group)

```php
Route::get('/overtime/submission-documents/types', [OvertimeController::class, 'listSubmissionDocumentTypes']);
Route::get('/overtime/submission-documents', [OvertimeController::class, 'listSubmissionDocuments']);
Route::post('/overtime/submission-documents', [OvertimeController::class, 'uploadSubmissionDocument']);
Route::get('/overtime/submission-documents/{id}', [OvertimeController::class, 'showSubmissionDocument']);
Route::get('/overtime/submission-documents/{id}/download', [OvertimeController::class, 'downloadSubmissionDocument']);
Route::get('/overtime/batches/{batchId}/submission-readiness', [OvertimeController::class, 'getBatchSubmissionReadiness']);
```

Place static `/overtime/submission-documents/types` before `/{id}` routes.

---

## 10. Error Codes

| Code | HTTP | When |
|------|------|------|
| `DOCUMENT_MISSING` | 422 | No active doc covers `created_at` for required `document_type` |
| `DOCUMENT_EXPIRED` | 422 | Doc covers date but `period_end < today` |
| `DOCUMENT_OVERLAP` | 422 | Upload overlaps existing active doc (same type) |
| `INVOICE_GENERATION_FAILED` | 422 | Invoice PDF failed at submit |
| `BATCH_NOT_DRAFT` | 409 | Submit attempted on non-draft batch |
| `UNAUTHORIZED` | 403 | Non-reviewer |

---

## 11. File Checklist

| Action | Path |
|--------|------|
| Create | `database/migrations/2026_06_18_000000_create_overtime_submission_documents_table.php` |
| Create | `config/overtime.php` |
| Create | `app/Models/Bms/OvertimeSubmissionDocument.php` |
| Create | `app/Services/Overtime/OvertimeSubmissionDocumentService.php` |
| Modify | `app/Http/Controllers/Bms/OvertimeController.php` |
| Modify | `app/Services/Overtime/OvertimeBatchService.php` |
| Modify | `routes/api.php` |
| Create (optional) | `tests/Feature/OvertimeSubmissionDocumentTest.php` |

---

## 12. Implementation Phases

### Phase 1 — Foundation (Day 1)

- [x] Migration + model
- [x] `config/overtime.php`
- [x] `OvertimeSubmissionDocumentService` — upload, overlap check, period calculation

### Phase 2 — APIs (Day 1–2)

- [x] Upload, list, show, download endpoints
- [x] Types helper endpoint
- [ ] Unit/feature tests for upload validation and overlap reject

### Phase 3 — Readiness (Day 2)

- [x] `resolveForBatch()` + `assessBatchReadiness()`
- [x] `GET .../submission-readiness`
- [x] Enrich `getBatch()` with `submission_readiness`

### Phase 4 — eOffice gate (Day 2–3)

- [x] Gate `saveAndSubmitBatch` before OAuth
- [x] Build MAIN + ATTACHMENT array from registry
- [ ] End-to-end test: upload → readiness OK → submit payload has 2 attachments

### Phase 5 — Frontend (separate track)

- [ ] Document upload screen (period start, preset/custom, PDF)
- [ ] Document list with status (active / expired)
- [ ] Batch detail: show readiness + disable eOffice submit when blocked

---

## 13. Test Scenarios

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Upload approval_memo covering batch `created_at` | `status: valid` on readiness |
| 2 | Submit batch with valid approval_memo + invoice | eOffice receives 2 attachments |
| 3 | Submit without upload | 422 `DOCUMENT_MISSING` |
| 4 | Doc covers `created_at` but `period_end` passed | 422 `DOCUMENT_EXPIRED` |
| 5 | Upload overlaps active same `document_type` | 422 `DOCUMENT_OVERLAP`, old doc unchanged |
| 6 | Upload after prior doc expired | 201 success |
| 7 | Multi-month batch, created Mar 15 | Match on Mar 15 only |
| 8 | `period_start` mid-month + `3_months` preset | `period_end = start + 3 months - 1 day` |
| 9 | Non-reviewer upload/submit | 403 |
| 10 | Different `document_type` overlapping periods | Allowed (overlap is per type) |

---

## 14. Out of Scope (v1)

- Auto-supersede or manual revoke/deactivate endpoint
- ERMS submit document gate (eOffice only)
- Cron to mark `status = expired` (expiry checked at submit time; optional later)
- Linking document rows to `batch_id`
- Payroll or other modules sharing the table

---

## 15. Rollout

1. Run migration on `bcmis2`: `php artisan migrate`
2. Deploy backend
3. Upload at least one active `approval_memo` document before first eOffice batch submit
4. Enable frontend submit guard using `can_submit_to_eoffice`
5. Monitor logs: document resolution, eOffice attachment count, 422 blocking reasons

---

## 16. Quick Reference — Resolution Rule

```text
reference_date = date(overtime_batches.created_at)

SELECT * FROM overtime_submission_documents
WHERE document_type = :required_type
  AND status = 'active'
  AND period_start <= reference_date
  AND period_end >= reference_date
  AND period_end >= CURDATE()
ORDER BY uploaded_at DESC, id DESC
LIMIT 1
```

If no row → block eOffice submit until user uploads a new document with a period covering the batch creation date.
