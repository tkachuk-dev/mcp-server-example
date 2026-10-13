<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal\Ai;

use App\Dto\Ai\GetChurnRiskDto;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class GetChurnRiskRequest extends FormRequest
{
    public const int MIN_LIMIT = 1;

    public const int MAX_LIMIT = 200;

    public const int DEFAULT_LIMIT = 50;

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
            'limit' => ['sometimes', 'integer', 'min:'.self::MIN_LIMIT, 'max:'.self::MAX_LIMIT],
        ];
    }

    public function toDto(): GetChurnRiskDto
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return new GetChurnRiskDto($tenant->id, $this->integer('limit', self::DEFAULT_LIMIT));
    }
}
