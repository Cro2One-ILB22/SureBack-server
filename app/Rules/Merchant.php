<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\InvokableRule;

class Merchant implements InvokableRule
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
        $merchant = User::where('id', $value)->first();
        if (!$merchant || !$merchant->isMerchant()) {
            $fail(__('The :attribute must be a valid merchant id.', ['attribute' => $attribute]));
        }
    }
}
