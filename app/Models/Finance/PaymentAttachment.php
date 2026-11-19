<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttachment extends Model
{
    protected $table = 'payment_attachments';

    protected $primaryKey = 'attachment_id';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'file_name',
        'file_path',
        'media_type',
        'file_size_kb',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_kb' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }
}
