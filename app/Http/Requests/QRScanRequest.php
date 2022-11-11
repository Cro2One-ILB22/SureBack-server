<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QRScanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'purchase_amount' => 'required|integer',
            'customer_id' => 'required|integer',
            'used_coins' => 'integer|nullable',
            'is_requesting_for_token' => 'boolean|nullable',
        ];
    }

    public function setDefaultValues()
    {
        $this->merge([
            'used_coins' => $this->used_coins ?? 0,
            'is_requesting_for_token' => $this->is_requesting_for_token ?? false,
        ]);
    }
}
