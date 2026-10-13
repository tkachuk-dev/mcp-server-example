<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\UserProjectionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\UserProjectionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Local projection of a user owned by the Users service; user_id is issued there.
 *
 * @property int $user_id
 * @property int $tenant_id
 * @property UserProjectionStatus $status
 * @property CarbonImmutable|null $last_event_at
 */
class UserProjection extends Model
{
    /** @use HasFactory<UserProjectionFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'tenant_id', 'status', 'last_event_at'];

    protected function casts(): array
    {
        return [
            'status' => UserProjectionStatus::class,
            'last_event_at' => 'immutable_datetime',
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
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id', 'user_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function paidOrders(): HasMany
    {
        return $this->orders()->where('status', OrderStatus::Paid);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', UserProjectionStatus::Active);
    }
}
