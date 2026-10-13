<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $order_id
 * @property int $payment_id
 * @property int $amount_minor
 * @property CarbonImmutable $refunded_at
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected $fillable = ['tenant_id', 'order_id', 'payment_id', 'amount_minor', 'refunded_at'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
