<?php

namespace App\Http\Requests;

use App\Rules\Customer;
use Illuminate\Foundation\Http\FormRequest;

class QRScanResponseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'customer_id' => [new Customer],
        ];
    }
}
