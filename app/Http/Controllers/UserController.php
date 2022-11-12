<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\UpdateMerchantDetailRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\MerchantDetail;
use App\Models\User;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function merchant()
    {
        $merchants = User::whereHas('roles', function ($query) {
            $query->where('slug', RoleEnum::MERCHANT);
        })->with('merchantDetail');

        $name = 'name';
        if (request()->has($name)) {
            $merchants = $merchants->where($name, 'like', '%' . request()->$name . '%');
        }

        $merchants = $merchants->paginate();

        return response()->json($merchants);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function customer()
    {
        $customers = User::whereHas('roles', function ($query) {
            $query->where('slug', RoleEnum::CUSTOMER);
        });

        $name = 'name';
        if (request()->has($name)) {
            $customers = $customers->where($name, 'like', '%' . request()->$name . '%');
        }

        $customers = $customers->paginate();

        return response()->json($customers);
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
