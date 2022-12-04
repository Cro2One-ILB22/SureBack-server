<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\MerchantDetail;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class UserService
{
    public function __construct()
    {
        $this->haversine = '(6371 * acos(
                                cos(radians(?))
                                * cos(radians(latitude))
                                * cos(radians(longitude) - radians(?))
                                + sin(radians(?))
                                * sin(radians(latitude))
                            ))';
    }

    function getMerchants(User $user, array $params = [], bool $isFavorite = null, bool $isVisited = null, array $location = [], float $radius = null, string $orderBy = null)
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

        $merchants = $merchants
            ->with(['merchantDetail' => function ($query) use ($userId, $location) {
                $query->withLastTokenGeneratedForMeAt($userId);
                $query->with('addresses.location');

                if (count($location) === 2) {
                    $latitude = $location[0];
                    $longitude = $location[1];
                    if (!is_numeric($latitude) || !is_numeric($longitude)) {
                        throw new BadRequestException('Invalid latitude or longitude');
                    }

                    $query->with(['addresses.location' => function ($query) use ($latitude, $longitude) {
                        $query->withDistance($latitude, $longitude);
                    }]);
                }
            }]);

        if (count($location) === 2) {
            $latitude = $location[0];
            $longitude = $location[1];

            $merchants = $merchants
                ->orderBy(
                    MerchantDetail::whereColumn('merchant_details.id', 'users.id')
                        ->join('addresses', function ($join) {
                            $join->on('addresses.addressable_id', '=', 'merchant_details.id')
                                ->where('addresses.addressable_type', '=', MerchantDetail::class);
                        })
                        ->join('locations', 'locations.id', '=', 'addresses.location_id')
                        ->selectRaw("{$this->haversine} as distance", [$latitude, $longitude, $latitude])
                        ->limit(1),
                )
                ->whereHas('merchantDetail', function ($query) use ($latitude, $longitude, $radius) {
                    $query->whereHas('addresses', function ($query) use ($latitude, $longitude, $radius) {
                        $query->whereHas('location', function ($query) use ($latitude, $longitude, $radius) {
                            $query->distance($latitude, $longitude, $radius);
                        });
                    });
                });
        }

        if ($orderBy) {
            $orderBy = explode(',', $orderBy);
            foreach ($orderBy as $order) {
                $order = explode(':', $order);
                if (count($order) !== 2) {
                    throw new BadRequestException('Invalid order by');
                }
                $column = $order[0];
                $direction = $order[1];
                if (!in_array($direction, ['asc', 'desc'])) {
                    throw new BadRequestException('Invalid order by');
                }
                if (!Schema::hasColumn('users', $column)) {
                    if ($column === 'is_favorite') {
                        $merchants = $merchants
                            ->withCount(['customersWhoFavoriteMe' => function ($query) use ($userId) {
                                $query->where('customer_id', $userId);
                            }]);
                        $column = 'customers_who_favorite_me_count';
                    } else if ($column === 'is_visited') {
                        $merchants = $merchants
                            ->withCount(['merchantCoins' => function ($query) use ($userId) {
                                $query->where('customer_id', $userId);
                            }]);
                        $column = 'merchant_coins_count';
                    } else {
                        throw new BadRequestException('Invalid order by');
                    }
                }
                $merchants = $merchants->orderBy($column, $direction);
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
            ->with(['merchantCoins' => function ($query) use ($user) {
                $query->where('customer_id', '=', $user->id);
            }])
            ->paginate()
            ->through(function ($merchant) use ($userId) {
                $merchant->individual_coins = $merchant->merchantCoins;
                unset($merchant->merchantCoins);
                $merchant->customerIdFilter($userId);

                return $merchant->append('is_favorite');
            });
    }

    function getMerchant(User $user, int $merchantId, array $location = [])
    {
        $merchant = User::whereHas('roles', function ($query) {
            $query->where('slug', RoleEnum::MERCHANT);
        })
            ->where('id', $merchantId)
            ->with(['merchantDetail' => function ($query) use ($user, $location) {
                $query->withLastTokenGeneratedForMeAt($user->id);
                $query->with('addresses.location');

                if (count($location) === 2) {
                    $latitude = $location[0];
                    $longitude = $location[1];
                    if (!is_numeric($latitude) || !is_numeric($longitude)) {
                        throw new BadRequestException('Invalid latitude or longitude');
                    }

                    $query->with(['addresses.location' => function ($query) use ($latitude, $longitude) {
                        $query->withDistance($latitude, $longitude);
                    }]);
                }
            }]);

        $merchant = $merchant->with('merchantCoins')
            ->with(['merchantCoins' => function ($query) use ($user) {
                $query->where('customer_id', '=', $user->id);
            }])
            ->first();

        if (!$merchant) {
            throw new BadRequestException('Merchant not found');
        }

        $merchant->individual_coins = $merchant->merchantCoins;
        unset($merchant->merchantCoins);
        $merchant->customerIdFilter($user->id);

        return $merchant->append('is_favorite');
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
            ->with(['customerCoins' => function ($query) use ($user) {
                $query->where('merchant_id', '=', $user->id);
            }]);
    }
}
