<?php

namespace App\Http\Controllers;

use App\Services\PushService;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function __construct(private PushService $push) {}

    public function vapidPublicKey()
    {
        return response()->json(['publicKey' => config('services.vapid.public_key')]);
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'min:1'],
            'keys.p256dh' => ['required', 'string', 'min:1'],
            'keys.auth' => ['required', 'string', 'min:1'],
        ]);

        $this->push->subscribe($request->user()->id, $validated['endpoint'], $validated['keys']['p256dh'], $validated['keys']['auth']);

        return response()->noContent();
    }

    public function unsubscribe(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'min:1'],
        ]);

        $this->push->unsubscribe($request->user()->id, $validated['endpoint']);

        return response()->noContent();
    }
}
