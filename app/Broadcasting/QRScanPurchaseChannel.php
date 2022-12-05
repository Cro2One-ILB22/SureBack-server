<?php

namespace App\Broadcasting;

use App\Enums\RoleEnum;
use App\Models\User;

class QRScanPurchaseChannel
{
    /**
     * Create a new channel instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  \App\Models\User  $user
     * @return array|bool
     */
    public function join(User $user, $merchantId, $customerId)
    {
        $userRoles = $user->roles->pluck('slug');
        $isMerchant = $userRoles->contains(RoleEnum::MERCHANT);
        $isCustomer = $userRoles->contains(RoleEnum::CUSTOMER);

        if ((int) $user->id === (int) $merchantId && $isMerchant) {
            $customer = User::where('id', $customerId)->whereHas('roles', function ($query) {
                $query->where('slug', RoleEnum::CUSTOMER);
            })->first();
            if ($customer) {
                return true;
            }
        }
        if ((int) $user->id === (int) $customerId && $isCustomer) {
            $merchant = User::where('id', $merchantId)->whereHas('roles', function ($query) {
                $query->where('slug', RoleEnum::MERCHANT);
            })->first();
            if ($merchant) {
                return true;
            }
        }
        return false;
    }
}
