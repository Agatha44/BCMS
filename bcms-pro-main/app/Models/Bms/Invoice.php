<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'batch_number',
        'pf_number',
        'employee_name',
        'customer_bank_account_no',
        'amount',
        'description',
        'accts_pay_code_combination',
        'created_by',
        'expense_accounts',
        'vendor',
        'vendor_site_id',
        'terms_id',
        'bank_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bank_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Batch relationship (via batch_number).
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(OvertimeBatch::class, 'batch_number', 'batch_number');
    }

    /**
     * Bank relationship.
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id', 'bank_id');
    }

    /**
     * Check if an invoice exists for a given batch and employee.
     */
    public static function existsForBatchAndEmployee(string $batchNumber, string $pfNumber): bool
    {
        return static::where('batch_number', $batchNumber)
            ->where('pf_number', $pfNumber)
            ->exists();
    }
}


