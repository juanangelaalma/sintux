<?php

namespace Modules\Expense\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Expense\Enums\ExpenseSource;
use Modules\Expense\Enums\ExpenseStatus;
use Modules\Expense\Enums\WithholdingType;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $number
 * @property int $sequence
 * @property Carbon $transaction_date
 * @property int|null $pay_from_account_id
 * @property bool $is_pay_later
 * @property int|null $contact_id
 * @property string|null $contact_name
 * @property string|null $billing_address
 * @property int|null $payment_method_id
 * @property string $currency_code
 * @property bool $is_tax_inclusive
 * @property WithholdingType|null $withholding_type
 * @property float $withholding_value
 * @property int|null $withholding_account_id
 * @property float $withholding_total
 * @property string|null $memo
 * @property float $subtotal
 * @property float $tax_total
 * @property float $grand_total
 * @property float $amount_paid
 * @property ExpenseStatus $status
 * @property ExpenseSource $source
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ExpenseLine> $lines
 */
class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'number',
        'sequence',
        'transaction_date',
        'pay_from_account_id',
        'is_pay_later',
        'contact_id',
        'contact_name',
        'billing_address',
        'payment_method_id',
        'currency_code',
        'is_tax_inclusive',
        'withholding_type',
        'withholding_value',
        'withholding_account_id',
        'withholding_total',
        'memo',
        'subtotal',
        'tax_total',
        'grand_total',
        'amount_paid',
        'status',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'is_pay_later' => 'boolean',
            'is_tax_inclusive' => 'boolean',
            'withholding_type' => WithholdingType::class,
            'withholding_value' => 'decimal:4',
            'withholding_total' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'amount_paid' => 'decimal:4',
            'status' => ExpenseStatus::class,
            'source' => ExpenseSource::class,
        ];
    }

    /**
     * @return HasMany<ExpenseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<ExpenseTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ExpenseTag::class, 'expense_expense_tag');
    }

    /**
     * @return HasMany<ExpenseAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    /**
     * Sisa tagihan yang belum dilunasi. Drives kolom "Sisa tagihan" di
     * daftar dan kartu "Biaya belum dibayar".
     */
    public function outstanding(): float
    {
        return round((float) $this->grand_total - (float) $this->amount_paid, 4);
    }

    /**
     * Label kolom "Kategori" di daftar biaya. Satu akun biaya ditampilkan
     * apa adanya; lebih dari satu akun ditampilkan sebagai "-Terbagi-".
     */
    public function categoryLabel(string $dividedLabel = '-Terbagi-'): string
    {
        $accountNames = $this->lines->pluck('account_name')->filter()->unique();

        if ($accountNames->count() === 1) {
            return (string) $accountNames->first();
        }

        return $accountNames->isEmpty() ? '-' : $dividedLabel;
    }
}
