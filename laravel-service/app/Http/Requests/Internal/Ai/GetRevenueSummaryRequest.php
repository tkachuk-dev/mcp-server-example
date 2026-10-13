<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal\Ai;

use App\Dto\Ai\GetRevenueSummaryDto;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class GetRevenueSummaryRequest extends FormRequest
{
    public const int MIN_DAYS = 1;

    public const int MAX_DAYS = 365;

    public const int DEFAULT_DAYS = 30;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', 'min:'.self::MIN_DAYS, 'max:'.self::MAX_DAYS],
        ];
    }

    public function toDto(): GetRevenueSummaryDto
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return new GetRevenueSummaryDto($tenant->id, $tenant->currency, $this->integer('days', self::DEFAULT_DAYS));
    }
}
