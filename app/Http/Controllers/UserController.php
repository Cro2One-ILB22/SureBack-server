<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\UpdateMerchantDetailRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\MerchantDetail;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

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
        ];
        $validator = request()->validate($rules);
        $params = request()->except(array_keys($rules));
        $isFavorite = $validator['is_favorite'] ?? null;
        $isVisited = $validator['is_visited'] ?? null;
        try {
            $merchants = $this->userService->getMerchants(auth()->user(), $params, $isFavorite, $isVisited)
                ->paginate()
                ->through(function ($merchant) {
                    $merchant->individual_coins = $merchant->merchantCoins;
                    unset($merchant->merchantCoins);
                    return $merchant;
                });
        } catch (BadRequestException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json($merchants);
    }

    public function merchant($id)
    {
        $user = auth()->user();
        $merchant = User::where('id', $id)
            ->whereHas('roles', function ($query) {
                $query->where('slug', RoleEnum::MERCHANT);
            })
            ->with('merchantDetail', 'merchantCoins')
            ->with(['merchantCoins' => function ($query) use ($user) {
                $query->where('customer_id', '=', $user->id);
            }])
            ->first();

        if (!$merchant) {
            return response()->json([
                'message' => 'Merchant not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $merchant->individual_coins = $merchant->merchantCoins;
        unset($merchant->merchantCoins);

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
        try {
            $customers = $this->userService->getCustomers(auth()->user(), $params, $hasFavoritedMe, $hasVisited)
                ->paginate()
                ->through(function ($customer) {
                    $customer->individual_coins = $customer->customerCoins;
                    unset($customer->customerCoins);
                    return $customer;
                });
        } catch (BadRequestException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

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
        try {
            $user->update($request->safe()->except('username'));
            return response()->json($user);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function updateMerchantDetail(UpdateMerchantDetailRequest $request)
    {
        $merchantDetail = auth()->user()->merchantDetail;
        $merchantDetail->update($request->validated());
        return response()->json($merchantDetail);
    }
}
