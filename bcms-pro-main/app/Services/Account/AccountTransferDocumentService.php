<?php

namespace App\Services\Account;

use App\Models\AccountTransfer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountTransferDocumentService
{
    public function validateUpload(UploadedFile $file): void
    {
        $maxKb = (int) config('account_transfer.approval_document.max_file_size_kb');
        $maxBytes = $maxKb * 1024;

        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'approval_document' => ["File must not exceed {$maxKb} KB."],
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $allowedExtensions = config('account_transfer.approval_document.allowed_extensions', ['pdf']);
        if (!in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'approval_document' => ['Only PDF files are allowed.'],
            ]);
        }

        $mime = strtolower((string) $file->getMimeType());
        $allowedMimes = array_map('strtolower', config('account_transfer.approval_document.allowed_mimes', ['application/pdf']));
        if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'approval_document' => ['Only PDF files are allowed.'],
            ]);
        }
    }

    public function store(string $transferUuid, UploadedFile $file): array
    {
        $this->validateUpload($file);

        $disk = (string) config('account_transfer.approval_document.disk', 'local');
        $directory = (string) config('account_transfer.approval_document.directory', 'account-transfers');
        $storageDir = $directory . '/' . $transferUuid;
        $storedName = $this->generateStoredFileName($file, $disk, $storageDir);

        $stored = Storage::disk($disk)->putFileAs($storageDir, $file, $storedName);
        if ($stored === false) {
            throw new RuntimeException('Failed to store approval document.');
        }

        $relativePath = $storageDir . '/' . $storedName;
        $originalName = basename(trim((string) $file->getClientOriginalName())) ?: $storedName;

        return [
            'path' => $relativePath,
            'name' => $originalName,
            'mime' => $file->getMimeType() ?: 'application/pdf',
        ];
    }

    public function replace(string $transferUuid, UploadedFile $file, ?string $oldPath = null): array
    {
        $stored = $this->store($transferUuid, $file);

        if ($oldPath !== null && $oldPath !== $stored['path']) {
            $this->delete($oldPath);
        }

        return $stored;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = (string) config('account_transfer.approval_document.disk', 'local');
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function exists(AccountTransfer $transfer): bool
    {
        if (!$transfer->hasApprovalDocument()) {
            return false;
        }

        $disk = (string) config('account_transfer.approval_document.disk', 'local');

        return Storage::disk($disk)->exists($transfer->approval_document_path);
    }

    public function download(AccountTransfer $transfer): StreamedResponse
    {
        $disk = (string) config('account_transfer.approval_document.disk', 'local');

        if (!$transfer->hasApprovalDocument() || !Storage::disk($disk)->exists($transfer->approval_document_path)) {
            throw ValidationException::withMessages([
                'approval_document' => ['Approval document file not found.'],
            ]);
        }

        return Storage::disk($disk)->download(
            $transfer->approval_document_path,
            $transfer->approval_document_name ?: 'approval_document.pdf',
            ['Content-Type' => $transfer->approval_document_mime ?: 'application/pdf']
        );
    }

    private function generateStoredFileName(UploadedFile $file, string $disk, string $storageDir): string
    {
        $originalName = basename(trim((string) $file->getClientOriginalName()));
        if ($originalName === '' || $originalName === '.' || $originalName === '..') {
            $sanitized = 'approval_document.pdf';
        } else {
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '') {
                $extension = 'pdf';
            }

            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^\w\- ]+/u', '_', $baseName) ?? 'approval_document';
            $safeBase = trim(preg_replace('/\s+/', ' ', $safeBase) ?? 'approval_document', ' ._-');
            if ($safeBase === '') {
                $safeBase = 'approval_document';
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
