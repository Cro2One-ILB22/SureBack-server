<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePartnerDetailRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\PartnerDetail;
use App\Models\User;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function partner()
    {
        $response = [
            'results' => User::whereHas('roles', function ($query) {
                $query->where('slug', 'partner');
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
                $query->where('slug', 'customer');
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

    public function updatePartnerDetail(UpdatePartnerDetailRequest $request)
    {
        $partnerDetail = PartnerDetail::where('user_id', auth()->user()->id)->first();
        $partnerDetail->update($request->validated());
        return response()->json($partnerDetail);
    }
}
