<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteWebPushSubscriptionRequest;
use App\Http\Requests\StoreWebPushSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class WebPushSubscriptionController extends Controller
{
    public function store(StoreWebPushSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $subscription = $request->validated();

        $user->updatePushSubscription(
            endpoint: $subscription['endpoint'],
            key: $subscription['keys']['p256dh'],
            token: $subscription['keys']['auth'],
            contentEncoding: $subscription['contentEncoding'] ?? null,
        );

        return response()->json([
            'subscribed' => true,
        ]);
    }

    public function destroy(DeleteWebPushSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->deletePushSubscription($request->validated('endpoint'));

        return response()->json([
            'subscribed' => false,
        ]);
    }
}
