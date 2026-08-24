<?php

namespace App\Services\Overtime;

use App\Models\Bms\OvertimeBatch;
use App\Models\Bms\OvertimeSubmissionDocument;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OvertimeSubmissionDocumentService
{
    private const PERIOD_DATE_FORMAT = 'Y-m-d';

    /**
     * @return array{period_start: Carbon, period_end: Carbon}
     */
    public function getDocumentPeriod(array $input): array
    {
        if (empty($input['period_start'])) {
            throw ValidationException::withMessages([
                'period_start' => ['Period start is required.'],
            ]);
        }

        if (empty($input['period_end'])) {
            throw ValidationException::withMessages([
                'period_end' => ['Period end is required.'],
            ]);
        }

        $periodStart = $this->periodDateFormat('period_start', (string) $input['period_start'])->startOfDay();
        $periodEnd = $this->periodDateFormat('period_end', (string) $input['period_end'])->endOfDay();

        if ($periodEnd->toDateString() < $periodStart->toDateString()) {
            throw ValidationException::withMessages([
                'period_end' => ['Period end must be on or after period start.'],
            ]);
        }

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    public function buildPeriodLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('j M Y');
        }

        if ($start->year === $end->year && $start->month === $end->month) {
            return $start->format('j') . '–' . $end->format('j M Y');
        }

        if ($start->year === $end->year) {
            return $start->format('j M') . ' – ' . $end->format('j M Y');
        }

        return $start->format('j M Y') . ' – ' . $end->format('j M Y');
    }

    /**
     * @return Collection<int, OvertimeSubmissionDocument>
     */
    public function findOverlappingActive(
        string $documentType,
        Carbon $start,
        Carbon $end,
        ?int $excludeId = null
    ): Collection {
        $query = OvertimeSubmissionDocument::query()
            ->ofType($documentType)
            ->active()
            ->notExpired()
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->orderByDesc('uploaded_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    public function toggleDocumentStatus(OvertimeSubmissionDocument $document): OvertimeSubmissionDocument
    {
        if ($document->isExpired() || $document->status === OvertimeSubmissionDocument::STATUS_EXPIRED) {
            throw ValidationException::withMessages([
                'status' => ['Expired documents cannot be toggled. Upload a new document instead.'],
            ]);
        }

        if ($document->status === OvertimeSubmissionDocument::STATUS_ACTIVE) {
            $document->status = OvertimeSubmissionDocument::STATUS_INACTIVE;
            $document->save();

            return $document->fresh() ?? $document;
        }

        if ($document->status === OvertimeSubmissionDocument::STATUS_INACTIVE) {
            if ($document->period_start === null || $document->period_end === null) {
                throw ValidationException::withMessages([
                    'status' => ['Document period is missing and cannot be reactivated.'],
                ]);
            }

            $periodStart = $document->period_start->copy()->startOfDay();
            $periodEnd = $document->period_end->copy()->endOfDay();

            $this->checkDocumentOverlapping(
                (string) $document->document_type,
                $periodStart,
                $periodEnd,
                $document->id
            );

            $document->status = OvertimeSubmissionDocument::STATUS_ACTIVE;
            $document->save();

            return $document->fresh() ?? $document;
        }

        throw ValidationException::withMessages([
            'status' => ['Document status cannot be toggled.'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDocument(
        OvertimeSubmissionDocument $document,
        array $input,
        ?UploadedFile $file,
        int $userId
    ): OvertimeSubmissionDocument {
        if ($document->status !== OvertimeSubmissionDocument::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => ['Only active documents can be updated.'],
            ]);
        }

        if ($document->isExpired()) {
            throw ValidationException::withMessages([
                'status' => ['Expired documents cannot be updated. Upload a new document instead.'],
            ]);
        }

        $hasDocumentName = array_key_exists('document_name', $input);
        $hasPeriodStart = !empty($input['period_start']);
        $hasPeriodEnd = !empty($input['period_end']);
        $hasFile = $file !== null;

        if (!$hasDocumentName && !$hasPeriodStart && !$hasPeriodEnd && !$hasFile) {
            throw ValidationException::withMessages([
                'document_name' => ['At least one field must be provided for update.'],
            ]);
        }

        if ($hasPeriodStart xor $hasPeriodEnd) {
            throw ValidationException::withMessages([
                'period_start' => ['Both period start and period end are required when updating the period.'],
                'period_end' => ['Both period start and period end are required when updating the period.'],
            ]);
        }

        $periodStart = $document->period_start?->copy()->startOfDay();
        $periodEnd = $document->period_end?->copy()->endOfDay();

        if ($hasPeriodStart && $hasPeriodEnd) {
            $period = $this->getDocumentPeriod($input);
            $periodStart = $period['period_start'];
            $periodEnd = $period['period_end'];
        }

        if ($periodStart === null || $periodEnd === null) {
            throw ValidationException::withMessages([
                'period_start' => ['Document period is missing and cannot be updated.'],
            ]);
        }

        if ($hasPeriodStart && $hasPeriodEnd) {
            $this->checkDocumentOverlapping(
                (string) $document->document_type,
                $periodStart,
                $periodEnd,
                $document->id
            );
        }

        $disk = (string) config('overtime.submission_documents.disk', 'local');
        $directory = trim((string) config('overtime.submission_documents.directory', 'overtime/submission-documents'), '/');
        $documentType = (string) $document->document_type;
        $oldPath = $document->file_path;
        $newRelativePath = null;

        if ($hasFile) {
            $this->validateDocumentUpload($file);
            $newRelativePath = $this->storeUploadedFile($file, $disk, $directory, $documentType);
        }

        try {
            return DB::connection('bcmis2')->transaction(function () use (
                $document,
                $input,
                $file,
                $userId,
                $hasDocumentName,
                $hasPeriodStart,
                $hasPeriodEnd,
                $hasFile,
                $periodStart,
                $periodEnd,
                $disk,
                $oldPath,
                $newRelativePath
            ) {
                if ($hasPeriodStart && $hasPeriodEnd) {
                    $this->checkDocumentOverlapping(
                        (string) $document->document_type,
                        $periodStart,
                        $periodEnd,
                        $document->id
                    );
                }

                if ($hasDocumentName) {
                    $documentName = trim((string) ($input['document_name'] ?? ''));
                    $document->document_name = $documentName === ''
                        ? $this->defaultDocumentNameForType((string) $document->document_type)
                        : $documentName;
                }

                if ($hasPeriodStart && $hasPeriodEnd) {
                    $document->period_start = $periodStart->toDateString();
                    $document->period_end = $periodEnd->toDateString();
                }

                if ($hasFile && $file !== null && $newRelativePath !== null) {
                    $document->file_path = $newRelativePath;
                    $document->file_name = $file->getClientOriginalName();
                    $document->mime_type = $file->getMimeType() ?: 'application/pdf';
                    $document->uploaded_at = now();
                    $document->uploaded_by = $userId;
                }

                $document->save();

                if ($hasFile && $oldPath !== null && $oldPath !== $newRelativePath) {
                    Storage::disk($disk)->delete($oldPath);
                }

                return $document->fresh() ?? $document;
            });
        } catch (\Throwable $e) {
            if ($newRelativePath !== null) {
                Storage::disk($disk)->delete($newRelativePath);
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function uploadDocument(array $input, UploadedFile $file, int $userId): OvertimeSubmissionDocument
    {
        $documentType = (string) ($input['document_type'] ?? '');
        $allowedTypes = config('overtime.submission_documents.types', []);
        if (!in_array($documentType, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'document_type' => ['Invalid document type.'],
            ]);
        }

        $period = $this->getDocumentPeriod($input);
        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        $this->checkDocumentOverlapping($documentType, $periodStart, $periodEnd);
        $this->validateDocumentUpload($file);

        $disk = (string) config('overtime.submission_documents.disk', 'local');
        $directory = trim((string) config('overtime.submission_documents.directory', 'overtime/submission-documents'), '/');
        $relativePath = $this->storeUploadedFile($file, $disk, $directory, $documentType);

        $documentName = trim((string) ($input['document_name'] ?? ''));
        if ($documentName === '') {
            $documentName = $this->defaultDocumentNameForType($documentType);
        }

        try {
            return DB::connection('bcmis2')->transaction(function () use (
                $documentType,
                $documentName,
                $periodStart,
                $periodEnd,
                $relativePath,
                $file,
                $userId
            ) {
                $this->checkDocumentOverlapping($documentType, $periodStart, $periodEnd);

                return OvertimeSubmissionDocument::query()->create([
                    'document_type' => $documentType,
                    'document_name' => $documentName,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'file_path' => $relativePath,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/pdf',
                    'status' => OvertimeSubmissionDocument::STATUS_ACTIVE,
                    'uploaded_by' => $userId,
                    'uploaded_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($relativePath);

            throw $e;
        }
    }

    public function resolveForBatch(OvertimeBatch $batch, string $documentType): ?OvertimeSubmissionDocument
    {
        $referenceDate = $batch->created_at?->toDateString();
        if ($referenceDate === null) {
            return null;
        }

        return OvertimeSubmissionDocument::query()
            ->ofType($documentType)
            ->active()
            ->coveringDate($referenceDate)
            ->notExpired()
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function assessBatchReadiness(OvertimeBatch $batch): array
    {
        $referenceDate = $batch->created_at?->toDateString();
        $requiredDocuments = [];
        $blockingReasons = [];
        $canSubmit = $referenceDate !== null;

        foreach (config('overtime.eoffice_attachments', []) as $spec) {
            if (!($spec['required'] ?? false)) {
                continue;
            }

            $documentType = (string) ($spec['document_type'] ?? '');
            $entry = [
                'document_type' => $documentType,
                'document_name' => (string) ($spec['document_name'] ?? $documentType),
                'required' => true,
                'status' => 'missing',
                'matched_document_id' => null,
                'period_start' => null,
                'period_end' => null,
                'uploaded_at' => null,
            ];

            if ($referenceDate === null) {
                $blockingReasons[] = [
                    'code' => 'BATCH_REFERENCE_DATE_MISSING',
                    'document_type' => $documentType,
                    'message' => 'Batch creation date is missing.',
                ];
                $canSubmit = false;
                $requiredDocuments[] = $entry;
                continue;
            }

            $matched = $this->resolveForBatch($batch, $documentType);
            if ($matched !== null) {
                $entry['status'] = 'valid';
                $entry['matched_document_id'] = $matched->id;
                $entry['file_name'] = $matched->file_name;
                $entry['period_start'] = $matched->period_start?->toDateString();
                $entry['period_end'] = $matched->period_end?->toDateString();
                $entry['uploaded_at'] = $matched->uploaded_at?->toIso8601String();
                $requiredDocuments[] = $entry;
                continue;
            }

            $expired = $this->getExpiredDocument($documentType, $referenceDate);
            if ($expired !== null) {
                $entry['status'] = 'expired';
                $entry['matched_document_id'] = $expired->id;
                $entry['file_name'] = $expired->file_name;
                $entry['period_start'] = $expired->period_start?->toDateString();
                $entry['period_end'] = $expired->period_end?->toDateString();
                $entry['uploaded_at'] = $expired->uploaded_at?->toIso8601String();
                $blockingReasons[] = [
                    'code' => 'DOCUMENT_EXPIRED',
                    'document_type' => $documentType,
                    'message' => sprintf(
                        'Document for type %s expired on %s. Upload a new document.',
                        $documentType,
                        $expired->period_end?->toDateString()
                    ),
                ];
                $canSubmit = false;
                $requiredDocuments[] = $entry;
                continue;
            }

            $blockingReasons[] = [
                'code' => 'DOCUMENT_MISSING',
                'document_type' => $documentType,
                'message' => sprintf(
                    'No active document of type %s covers batch creation date %s.',
                    $documentType,
                    $referenceDate
                ),
            ];
            $canSubmit = false;
            $requiredDocuments[] = $entry;
        }

        return [
            'batch_reference_date' => $referenceDate,
            'source' => 'created_at',
            'required_documents' => $requiredDocuments,
            'can_submit_to_eoffice' => $canSubmit,
            'blocking_reasons' => $blockingReasons,
        ];
    }

    public function readAsBase64(OvertimeSubmissionDocument $document): string
    {
        $disk = (string) config('overtime.submission_documents.disk', 'local');
        if (!Storage::disk($disk)->exists($document->file_path)) {
            throw new RuntimeException('Submission document file not found on disk.');
        }

        $binary = Storage::disk($disk)->get($document->file_path);
        if ($binary === null || $binary === '') {
            throw new RuntimeException('Submission document file is empty.');
        }

        return base64_encode($binary);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{success: true, data: array, pagination: array}|array{success: false, error: string, http: int}
     */
    public function listOvertimeSubmissionDocuments(array $filters): array
    {
        try {
            $query = OvertimeSubmissionDocument::query()->orderByDesc('uploaded_at');

            if (!empty($filters['document_type'])) {
                $query->ofType((string) $filters['document_type']);
            }

            if (!empty($filters['document_name'])) {
                $query->where('document_name', 'like', '%' . $filters['document_name'] . '%');
            }

            if (!empty($filters['status'])) {
                $query->where('status', (string) $filters['status']);
            }

            $perPage = max(1, (int) ($filters['per_page'] ?? 10));
            $paginator = $query->paginate($perPage);

            return [
                'success' => true,
                'data' => collect($paginator->items())->map(fn (OvertimeSubmissionDocument $doc) => $this->serializeDocument($doc))->values()->all(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to list overtime submission documents', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to list submission documents: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDocument(OvertimeSubmissionDocument $document): array
    {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'document_name' => $document->document_name,
            'period_start' => $document->period_start?->toDateString(),
            'period_end' => $document->period_end?->toDateString(),
            'period_label' => ($document->period_start && $document->period_end)
                ? $this->buildPeriodLabel($document->period_start, $document->period_end)
                : null,
            'file_name' => $document->file_name,
            'status' => $document->status,
            'uploaded_by' => $document->uploaded_by,
            'uploaded_at' => $document->uploaded_at?->toIso8601String(),
            'is_expired' => $document->isExpired(),
        ];
    }

    private function getExpiredDocument(string $documentType, string $referenceDate): ?OvertimeSubmissionDocument
    {
        return OvertimeSubmissionDocument::query()
            ->ofType($documentType)
            ->active()
            ->coveringDate($referenceDate)
            ->whereDate('period_end', '<', now()->toDateString())
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->first();
    }

    private function periodDateFormat(string $field, string $value): Carbon
    {
        $value = trim($value);
        if ($value === '') {
            throw ValidationException::withMessages([
                $field => ['Date is required.'],
            ]);
        }

        $parsed = Carbon::createFromFormat(self::PERIOD_DATE_FORMAT, $value);
        if ($parsed === false || $parsed->format(self::PERIOD_DATE_FORMAT) !== $value) {
            throw ValidationException::withMessages([
                $field => ['Date must be in ' . self::PERIOD_DATE_FORMAT . ' format.'],
            ]);
        }

        return $parsed;
    }

    private function checkDocumentOverlapping(
        string $documentType,
        Carbon $start,
        Carbon $end,
        ?int $excludeId = null
    ): void {
        $overlaps = $this->findOverlappingActive($documentType, $start, $end, $excludeId);
        if ($overlaps->isEmpty()) {
            return;
        }

        $existing = $overlaps->first();
        throw ValidationException::withMessages([
            'period_start' => [
                sprintf(
                    'Overlaps with document #%d (%s to %s).',
                    $existing->id,
                    $existing->period_start?->toDateString(),
                    $existing->period_end?->toDateString()
                ),
            ],
        ]);
    }

    private function validateDocumentUpload(UploadedFile $file): void
    {
        $maxKb = (int) config('overtime.submission_documents.max_file_size_kb');
        $maxBytes = $maxKb * 1024;

        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => ["File must not exceed {$maxKb} KB."],
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $allowedExtensions = config('overtime.submission_documents.allowed_extensions', ['pdf']);
        if (!in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => ['Only PDF files are allowed.'],
            ]);
        }

        $mime = strtolower((string) $file->getMimeType());
        $allowedMimes = array_map('strtolower', config('overtime.submission_documents.allowed_mimes', ['application/pdf']));
        if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => ['Only PDF files are allowed.'],
            ]);
        }
    }

    private function defaultDocumentNameForType(string $documentType): string
    {
        foreach (config('overtime.eoffice_attachments', []) as $spec) {
            if (($spec['document_type'] ?? null) === $documentType) {
                return (string) ($spec['document_name'] ?? ucfirst($documentType));
            }
        }

        return ucfirst($documentType);
    }

    private function storeUploadedFile(UploadedFile $file, string $disk, string $directory, string $documentType): string
    {
        $storageDir = $directory . '/' . $documentType;
        $storedName = $this->generateStoredFileName($file, $disk, $storageDir);

        $stored = Storage::disk($disk)->putFileAs($storageDir, $file, $storedName);
        if ($stored === false) {
            throw new RuntimeException('Failed to store submission document.');
        }

        return $storageDir . '/' . $storedName;
    }

    private function generateStoredFileName(UploadedFile $file, string $disk, string $storageDir): string
    {
        $originalName = basename(trim((string) $file->getClientOriginalName()));
        if ($originalName === '' || $originalName === '.' || $originalName === '..') {
            $sanitized = 'document.pdf';
        } else {
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '') {
                $extension = 'pdf';
            }

            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^\w\- ]+/u', '_', $baseName) ?? 'document';
            $safeBase = trim(preg_replace('/\s+/', ' ', $safeBase) ?? 'document', ' ._-');
            if ($safeBase === '') {
                $safeBase = 'document';
            }

            $sanitized = $safeBase . '.' . $extension;
        }

        $candidate = $sanitized;
        $counter = 1;

        while (Storage::disk($disk)->exists($storageDir . '/' . $candidate)) {
            $base = pathinfo($sanitized, PATHINFO_FILENAME);
            $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
            $suffix = $extension !== '' ? '.' . $extension : '';
            $candidate = $base . '_' . $counter . $suffix;
            $counter++;
        }

        return $candidate;
    }
}
