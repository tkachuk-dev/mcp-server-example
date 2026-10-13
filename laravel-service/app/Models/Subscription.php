<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $plan
 * @property int $amount_minor
 * @property SubscriptionStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $cancelled_at
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $fillable = ['tenant_id', 'user_id', 'plan', 'amount_minor', 'status', 'started_at', 'cancelled_at'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'amount_minor' => 'integer',
            'started_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<UserProjection, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(UserProjection::class, 'user_id', 'user_id');
    }
}
