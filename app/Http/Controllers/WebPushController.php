<?php

namespace App\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;

class WebPushController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = $request->user();

        try {
            $user->updatePushSubscription(
                $request->endpoint,
                $request->input('keys.p256dh'),
                $request->input('keys.auth'),
            );
        } catch (UniqueConstraintViolationException $e) {
            // Subscription already exists — idempotent
        }

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $subscription = PushSubscription::findOrFail($id);

        if ((string) $subscription->subscribable_id !== (string) auth()->id()) {
            abort(403);
        }

        $subscription->delete();

        return response()->json(['success' => true]);
    }
}
