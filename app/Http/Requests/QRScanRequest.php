<?php

namespace App\Http\Requests;

use App\Rules\Customer;
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
            'customer_id' => [new Customer],
            'coins_used' => 'integer|nullable',
            'is_requesting_for_token' => 'boolean|nullable',
        ];
    }

    public function setDefaultValues()
    {
        $this->merge([
            'coins_used' => $this->coins_used ?? 0,
            'is_requesting_for_token' => $this->is_requesting_for_token ?? false,
        ]);
    }
}
