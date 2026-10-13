<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property OrderStatus $status
 * @property int $amount_minor
 * @property CarbonImmutable|null $paid_at
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = ['tenant_id', 'user_id', 'status', 'amount_minor', 'paid_at'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount_minor' => 'integer',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<UserProjection, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(UserProjection::class, 'user_id', 'user_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function paid(Builder $query): void
    {
        $query->where('status', OrderStatus::Paid);
    }
}
