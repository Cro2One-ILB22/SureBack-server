<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\UpdateLocation;
use App\Http\Requests\UpdateMerchantDetailRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Location;
use App\Models\MerchantDetail;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct()
    {
        $this->userService = new UserService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function merchants()
    {
        $rules = [
            'is_favorite' => 'boolean',
            'is_visited' => 'boolean',
            'latitude' => 'numeric',
            'longitude' => 'numeric',
            'radius' => 'numeric',
            'order_by' => 'string',
        ];
        $validator = request()->validate($rules);
        $params = request()->except(array_keys($rules));
        $isFavorite = $validator['is_favorite'] ?? null;
        $isVisited = $validator['is_visited'] ?? null;
        $latitude = $validator['latitude'] ?? null;
        $longitude = $validator['longitude'] ?? null;
        $location = $latitude && $longitude ? [$latitude, $longitude] : [];
        $radius = $validator['radius'] ?? null;
        $orderBy = $validator['order_by'] ?? null;
        $user = auth()->user();

        $merchants = $this->userService->getMerchants($user, $params, $isFavorite, $isVisited, $location, $radius, $orderBy);

        return response()->json($merchants);
    }

    public function merchant($id)
    {
        $validator = request()->validate([
            'latitude' => 'numeric',
            'longitude' => 'numeric',
        ]);
        $latitude = $validator['latitude'] ?? null;
        $longitude = $validator['longitude'] ?? null;
        $location = $latitude && $longitude ? [$latitude, $longitude] : [];
        $user = auth()->user();

        $merchant = $this->userService->getMerchant($user, $id, $location);
        return response()->json($merchant);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function customers()
    {
        $rules = [
            'has_favorited_me' => 'boolean',
            'has_visited' => 'boolean',
        ];
        $validator = request()->validate($rules);
        $params = request()->except(array_keys($rules));
        $hasFavoritedMe = $validator['has_favorited_me'] ?? null;
        $hasVisited = $validator['has_visited'] ?? null;
        $customers = $this->userService->getCustomers(auth()->user(), $params, $hasFavoritedMe, $hasVisited)
            ->paginate()
            ->through(function ($customer) {
                $customer->individual_coins = $customer->customerCoins;
                unset($customer->customerCoins);
                return $customer;
            });

        return response()->json($customers);
    }

    public function customer($id)
    {
        $user = auth()->user();
        $customer = User::where('id', $id)
            ->whereHas('roles', function ($query) {
                $query->where('slug', RoleEnum::CUSTOMER);
            })
            ->with(['customerCoins' => function ($query) use ($user) {
                $query->where('merchant_id', '=', $user->id);
            }])
            ->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $customer->individual_coins = $customer->customerCoins;
        unset($customer->customerCoins);

        return response()->json($customer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateUserRequest  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUserRequest $request)
    {
        $user = auth()->user();
        $user->update($request->safe()->except('username'));
        return response()->json($user);
    }

    public function updateMerchantDetail(UpdateMerchantDetailRequest $request)
    {
        $merchantDetail = auth()->user()->merchantDetail;
        $merchantDetail->update($request->validated());
        return response()->json($merchantDetail);
    }

    public function updateLocation(UpdateLocation $request)
    {
        $user = auth()->user();
        DB::transaction(function () use ($user, $request) {
            if (!$user->addresses->first()) {
                $location = Location::create($request->validated());
                $user->addresses()->updateOrCreate([
                    'addressable_id' => $user->id,
                    'addressable_type' => User::class,
                ], [
                    'location_id' => $location->id,
                ]);
            } else {
                $user->addresses()->first()->location()->update($request->validated());
            }
        });

        return response()->json($user->load('addresses.location'));
    }

    public function updateMerchantLocation(UpdateLocation $request)
    {
        $user = auth()->user();
        DB::transaction(function () use ($user, $request) {
            if (!$user->merchantDetail->addresses->first()) {
                $location = Location::create($request->validated());
                $user->merchantDetail->addresses()->updateOrCreate([
                    'addressable_id' => $user->merchantDetail->id,
                    'addressable_type' => MerchantDetail::class,
                ], [
                    'location_id' => $location->id,
                ]);
            } else {
                $user->merchantDetail->addresses()->first()->location()->update($request->validated());
            }
        });

        return response()->json($user->load('merchantDetail.addresses.location'));
    }

    public function favoriteMerchant($id)
    {
        $user = auth()->user();
        $merchant = User::where('id', $id)
            ->whereHas('roles', function ($query) {
                $query->where('slug', RoleEnum::MERCHANT);
            })
            ->withIsFavoriteMerchant($user->id)
            ->first();

        if (!$merchant) {
            return response()->json([
                'message' => 'Merchant not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $user->favoriteMerchantsAsCustomer()->toggle($merchant);
        $merchant->is_favorite = !$merchant->is_favorite;

        return response()->json($merchant);
    }
}
