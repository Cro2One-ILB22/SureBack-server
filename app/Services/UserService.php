<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class UserService
{
    function getMerchants(User $user, array $params = [], bool $isFavorite = null, bool $isVisited = null)
    {
        $userId = $user->id;
        $merchants = User::whereHas('roles', function ($query) {
            $query->where('slug', RoleEnum::MERCHANT);
        });

        if ($isFavorite !== null) {
            if ($isFavorite) {
                $merchants = $user->favoriteMerchantsAsCustomer();
            } else {
                $merchants = $merchants
                    ->whereDoesntHave('customersWhoFavoriteMe', function ($query) use ($user) {
                        $query->whereIn('customer_id', [$user->id]);
                    });
            }
        }

        if ($isVisited !== null) {
            if ($isVisited) {
                $merchants = $merchants
                    ->whereHas('merchantCoins', function ($query) use ($userId) {
                        $query->where('customer_id', $userId);
                    });
            } else {
                $merchants = $merchants
                    ->whereDoesntHave('merchantCoins', function ($query) use ($userId) {
                        $query->where('customer_id', $userId);
                    });
            }
        }

        foreach ($params as $key => $value) {
            if (Schema::hasColumn('users', $key)) {
                if ($key === 'name') {
                    $value = $value ?? '';
                }
                $value = $value !== null ? strtolower($value) : '';
                $merchants = $merchants->whereRaw("LOWER($key) LIKE ?", ['%' . $value . '%']);
            } else {
                throw new BadRequestException("Invalid parameter: $key");
            }
        }

        return $merchants
            ->with('merchantDetail', 'merchantCoins.customer');
    }

    function getCustomers(User $user, array $params = [], bool $hasFavoritedMe = null, bool $hasVisited = null)
    {
        $userId = $user->id;
        $customers = User::whereHas('roles', function ($query) {
            $query->where('slug', RoleEnum::CUSTOMER);
        });

        if ($hasFavoritedMe !== null) {
            if ($hasFavoritedMe) {
                $customers = $user->customersWhoFavoriteMe();
            } else {
                $customers = $customers
                    ->whereDoesntHave('favoriteMerchantsAsCustomer', function ($query) use ($user) {
                        $query->whereIn('merchant_id', [$user->id]);
                    });
            }
        }

        if ($hasVisited !== null) {
            if ($hasVisited) {
                $customers = $customers
                    ->whereHas('customerCoins', function ($query) use ($userId) {
                        $query->where('merchant_id', $userId);
                    });
            } else {
                $customers = $customers
                    ->whereDoesntHave('customerCoins', function ($query) use ($userId) {
                        $query->where('merchant_id', $userId);
                    });
            }
        }

        foreach ($params as $key => $value) {
            if (Schema::hasColumn('users', $key)) {
                if ($key === 'name') {
                    $value = $value ?? '';
                }
                $value = $value !== null ? strtolower($value) : '';
                $customers = $customers->whereRaw("LOWER($key) LIKE ?", ['%' . $value . '%']);
            } else {
                throw new BadRequestException("Invalid parameter: $key");
            }
        }

        return $customers
            ->with('customerCoins.merchant');
    }
}
