<?php

declare(strict_types=1);

namespace App\Http\Requests\Internal\Ai;

use App\Dto\Ai\GetRfmSummaryDto;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class GetRfmSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function toDto(): GetRfmSummaryDto
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return new GetRfmSummaryDto($tenant->id, $tenant->currency);
    }
}
