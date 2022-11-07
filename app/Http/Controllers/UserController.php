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
        $response = [
            'results' => User::whereHas('roles', function ($query) {
                $query->where('slug', RoleEnum::MERCHANT);
            })->get(),
        ];

        return response()->json($response);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function customer()
    {
        $response = [
            'results' => User::whereHas('roles', function ($query) {
                $query->where('slug', RoleEnum::CUSTOMER);
            })->get(),
        ];

        return response()->json($response);
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
        $user = User::find(auth()->user()->id);
        try {
            $user->update($request->safe()->except('username'));
            return response()->json($user->first());
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
