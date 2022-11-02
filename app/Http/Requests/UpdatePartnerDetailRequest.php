<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerDetailRequest extends FormRequest
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
        ];
    }
}
