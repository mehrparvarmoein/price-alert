<?php

namespace App\Http\Requests;

use App\Domain\PriceAlert\Enums\AlertDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceAlertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_price' => ['required', 'integer', 'min:1','max:999999999'],
            'direction' => ['required', Rule::enum(AlertDirection::class)],
        ];
    }
}
