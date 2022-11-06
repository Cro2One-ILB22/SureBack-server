<?php

namespace App\Http\Controllers;

use App\Models\Ledger;

class TransactionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $transactions = Ledger::whereHas('transaction', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with('transaction.category')->get();

        $transactions = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'amount' => $transaction->transaction->amount,
                'category' => $transaction->transaction->category->slug,
                'description' => $transaction->transaction->description,
                'type' => $transaction->transaction->type,
                'instrument' => $transaction->instrument,
                'created_at' => $transaction->created_at,
            ];
        });

        $response = [
            'results' => $transactions,
        ];

        return response()->json($response);
    }
}
