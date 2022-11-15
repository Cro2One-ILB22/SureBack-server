<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::whereHas('subscription', function ($query) {
            $query->where('user_id', auth()->user()->id);
        })
            ->paginate();

        return response()->json($notifications);
    }
}
