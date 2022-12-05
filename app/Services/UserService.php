<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\MerchantDetail;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            ->with(['merchantDetail.user.purchasesAsMerchant', 'merchantDetail' => function ($query) use ($userId, $location) {
                $query->withLastTokenGeneratedForMeAt($userId)
                    ->todaysTokenCount()
                    ->with('addresses.location');

                if (count($location) === 2) {
                    $latitude = $location[0];
                    $longitude = $location[1];
                    if (!is_numeric($latitude) || !is_numeric($longitude)) {
                        throw new BadRequestHttpException('Invalid latitude or longitude');
                    }

                    $query->with(['addresses.location' => function ($query) use ($latitude, $longitude) {
                        $query->withDistance($latitude, $longitude);
                    }]);
                }
            }])
            ->withIsFavoriteMerchant($userId);

        foreach ($params as $key => $value) {
            if (Schema::hasColumn('users', $key)) {
                if ($key === 'name') {
                    $value = $value ?? '';
                }
                $value = $value !== null ? strtolower($value) : '';
                $merchants = $merchants->whereRaw("LOWER($key) LIKE ?", ['%' . $value . '%']);
            } else {
                throw new BadRequestHttpException("Invalid parameter: $key");
            }
        }

        if ($orderBy) {
            $orderBy = explode(',', $orderBy);
            foreach ($orderBy as $order) {
                $order = explode(':', $order);
                if (count($order) !== 2) {
                    throw new BadRequestHttpException('Invalid order by');
                }
                $column = $order[0];
                $direction = $order[1];
                if (!in_array($direction, ['asc', 'desc'])) {
                    throw new BadRequestHttpException('Invalid order by');
                }
                if (!Schema::hasColumn('users', $column)) {
                    if ($column === 'is_favorite') {
                    } else if ($column === 'is_visited') {
                        $merchants = $merchants
                            ->withCount(['merchantCoins' => function ($query) use ($userId) {
                                $query->where('customer_id', $userId);
                            }]);
                        $column = 'merchant_coins_count';
                    } else {
                        throw new BadRequestHttpException('Invalid order by');
                    }
                }
                $merchants = $merchants->orderBy($column, $direction);
            }
        }

        if (count($location) === 2) {
            $latitude = $location[0];
            $longitude = $location[1];

            $merchants = $merchants
                ->whereHas('merchantDetail', function ($query) use ($latitude, $longitude, $radius) {
                    $query->whereHas('addresses', function ($query) use ($latitude, $longitude, $radius) {
                        $query->whereHas('location', function ($query) use ($latitude, $longitude, $radius) {
                            $query->distance($latitude, $longitude, $radius);
                        });
                    });
                })
                ->orderBy(
                    MerchantDetail::whereColumn('merchant_details.user_id', 'users.id')
                        ->join('addresses', function ($join) {
                            $join->on('addresses.addressable_id', '=', 'merchant_details.id')
                                ->where('addresses.addressable_type', '=', MerchantDetail::class);
                        })
                        ->join('locations', 'locations.id', '=', 'addresses.location_id')
                        ->selectRaw("{$this->haversine} as distance", [$latitude, $longitude, $latitude])
                        ->limit(1),
                );
        }

        return $merchants
            ->with(['merchantCoins' => function ($query) use ($user) {
                $query->where('customer_id', '=', $user->id);
            }])
            ->paginate()
            ->through(function ($merchant) {
                $merchant->merchantDetail->makeHidden('user');
                $merchant->individual_coins = $merchant->merchantCoins;
                unset($merchant->merchantCoins);

                return $merchant;
            });
    }

    function getMerchant(User $user, int $merchantId, array $location = [])
    {
        $merchant = User::whereHas('roles', function ($query) {
            $query->where('slug', RoleEnum::MERCHANT);
        })
            ->where('id', $merchantId)
            ->with(['merchantDetail.user.purchasesAsMerchant', 'merchantDetail' => function ($query) use ($user, $location) {
                $query->withLastTokenGeneratedForMeAt($user->id)
                    ->todaysTokenCount()
                    ->with('addresses.location');

                if (count($location) === 2) {
                    $latitude = $location[0];
                    $longitude = $location[1];
                    if (!is_numeric($latitude) || !is_numeric($longitude)) {
                        throw new BadRequestHttpException('Invalid latitude or longitude');
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
            ->withIsFavoriteMerchant($user->id)
            ->first();

        if (!$merchant) {
            throw new NotFoundHttpException('Merchant not found');
        }

        $merchant->merchantDetail->makeHidden('user');
        $merchant->individual_coins = $merchant->merchantCoins;
        unset($merchant->merchantCoins);

        return $merchant;
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
                throw new BadRequestHttpException("Invalid parameter: $key");
            }
        }

        return $customers
            ->with(['customerCoins' => function ($query) use ($user) {
                $query->where('merchant_id', '=', $user->id);
            }]);
    }
}
