<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\InvokableRule;

class Customer implements InvokableRule
{
    /**
     * Indicates whether the rule should be implicit.
     *
     * @var bool
     */
    public $implicit = true;

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function __invoke($attribute, $value, $fail)
    {
        $customer = User::where('id', $value)->first();
        if (!$customer || !$customer->isCustomer()) {
            $fail(__('The :attribute must be a valid customer id.', ['attribute' => $attribute]));
        }
    }
}
