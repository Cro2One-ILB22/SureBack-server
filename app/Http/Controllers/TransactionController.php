<?php

namespace App\Http\Controllers;

use App\Models\SuccessfulTransaction;

class TransactionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $transactions = SuccessfulTransaction::whereHas('transaction', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with('transaction.category')->get();

        $transactions = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'amount' => $transaction->transaction->amount,
                'balance_after' => $transaction->balance_after,
                'balance_before' => $transaction->balance_before,
                'points_before' => $transaction->points_before,
                'points_after' => $transaction->points_after,
                'category' => $transaction->transaction->category->slug,
                'description' => $transaction->transaction->description,
                'type' => $transaction->transaction->type,
                'created_at' => $transaction->created_at,
            ];
        });

        $response = [
            'results' => $transactions,
        ];

        return response()->json($response);
    }
}
