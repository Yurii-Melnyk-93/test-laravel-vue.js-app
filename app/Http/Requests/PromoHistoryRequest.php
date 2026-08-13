<?php

namespace App\Http\Requests;

use App\Enums\ClaimStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoHistoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Absent means "every status".
            'status' => ['nullable', Rule::enum(ClaimStatus::class)],

            // Capped so a single request cannot ask for the whole table.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function status(): ?ClaimStatus
    {
        $status = $this->validated('status');

        return $status === null ? null : ClaimStatus::from($status);
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 10);
    }
}
