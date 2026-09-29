<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $journal_date
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $reversal_of_id
 * @property string|null $memo
 * @property string $status
 */
class Journal extends Model
{
    protected $fillable = [
        'branch_id',
        'journal_date',
        'reference_type',
        'reference_id',
        'reversal_of_id',
        'memo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
        ];
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
