<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'accepted'],
        ];
    }
}
