<?php

namespace App\Models\Inventory;

use App\Enums\GoodsIssueStatus;
use App\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsIssue extends Model
{
    protected $primaryKey = 'goods_issue_id';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['posting_key'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'posted_at' => 'datetime', 'status' => GoodsIssueStatus::class];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsIssueItem::class, 'goods_issue_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
