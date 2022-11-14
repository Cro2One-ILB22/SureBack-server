<?php

namespace App\Http\Requests;

use App\Enums\CashbackCalculationMethodEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMerchantDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'cashback_percent' => 'numeric|between:0,100|nullable',
            'cashback_limit' => 'numeric|nullable',
            'daily_token_limit' => 'numeric|nullable',
            'is_active_generating_token' => 'boolean',
            'cashback_calculation_method' => 'string|in:' . implode(',', CashbackCalculationMethodEnum::values()),
        ];
    }
}
