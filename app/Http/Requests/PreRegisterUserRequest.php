<?php

namespace App\Http\Requests;

use App\Enums\RegisterableRoleEnum;
use Illuminate\Foundation\Http\FormRequest;

class PreRegisterUserRequest extends FormRequest
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
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6',
            'username' => 'required|string',
            'role' => 'required|string|in:' . implode(',', RegisterableRoleEnum::values()),
        ];
    }
}
