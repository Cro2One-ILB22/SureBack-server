<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
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
            'identifier' => 'required|string',
            'name' => 'string|nullable',
            'os' => 'string|nullable',
            'os_version' => 'string|nullable',
            'model' => 'string|nullable',
            'notification_token' => 'required|string',
        ];
    }
}
