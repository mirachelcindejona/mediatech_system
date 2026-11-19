<?php

namespace App\Models\Finance;

use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $table = 'payments';

    protected $primaryKey = 'payment_id';

    public $timestamps = false;

    protected $fillable = [
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'bank_account_id',
        'reference_number',
        'notes',
        'recorded_by',
        'validated_by',
        'validated_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'validated_at' => 'datetime',
            'status' => PaymentStatus::class,
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id', 'bank_account_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'user_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by', 'user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PaymentAttachment::class, 'payment_id', 'payment_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id', 'payment_id');
    }
}
