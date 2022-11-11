<?php

namespace App\Http\Controllers;

use App\Models\FinancialTransaction;

class TransactionController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $transactions = FinancialTransaction::where('user_id', $user->id)->with('status', 'category', 'paymentInstrument')->get()->map(function ($transaction) {
            return [
                ...$transaction->toArray(),
                'status' => $transaction->status->slug,
                'category' => $transaction->category->slug,
                'payment_instrument' => $transaction->paymentInstrument->slug,
            ];
        });

        $response = [
            'results' => $transactions,
        ];

        return response()->json($response);
    }
}
