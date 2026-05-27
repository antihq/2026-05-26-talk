<?php

use App\Models\User;

test('guest cannot subscribe to push notifications', function () {
    $response = $this->postJson(route('webpush.subscribe'), [
        'endpoint' => 'https://fcm.googleapis.com/...',
        'keys' => [
            'p256dh' => 'test-p256dh-key',
            'auth' => 'test-auth-key',
        ],
    ]);

    $response->assertUnauthorized();
});

test('authenticated user can subscribe to push notifications', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('webpush.subscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/test-endpoint',
            'keys' => [
                'p256dh' => 'test-p256dh-key',
                'auth' => 'test-auth-key',
            ],
        ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $this->assertDatabaseHas('push_subscriptions', [
        'subscribable_id' => $user->id,
        'subscribable_type' => User::class,
        'endpoint' => 'https://fcm.googleapis.com/test-endpoint',
    ]);
});

test('subscribe validates endpoint is required', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('webpush.subscribe'), [
            'keys' => [
                'p256dh' => 'test',
                'auth' => 'test',
            ],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['endpoint']);
});

test('subscribe validates keys are required', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('webpush.subscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/...',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
});

test('guest cannot unsubscribe from push notifications', function () {
    $user = User::factory()->create();
    $subscription = $user->pushSubscriptions()->create([
        'endpoint' => 'https://fcm.googleapis.com/test',
        'public_key' => 'key',
        'auth_token' => 'token',
    ]);

    $response = $this->deleteJson(route('webpush.unsubscribe', $subscription->id));

    $response->assertUnauthorized();
});

test('authenticated user can unsubscribe from push notifications', function () {
    $user = User::factory()->create();
    $subscription = $user->pushSubscriptions()->create([
        'endpoint' => 'https://fcm.googleapis.com/test',
        'public_key' => 'key',
        'auth_token' => 'token',
    ]);

    $response = $this
        ->actingAs($user)
        ->deleteJson(route('webpush.unsubscribe', $subscription->id));

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $this->assertDatabaseMissing('push_subscriptions', [
        'id' => $subscription->id,
    ]);
});

test('user cannot delete another users subscription', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $subscription = $owner->pushSubscriptions()->create([
        'endpoint' => 'https://fcm.googleapis.com/test',
        'public_key' => 'key',
        'auth_token' => 'token',
    ]);

    $response = $this
        ->actingAs($attacker)
        ->deleteJson(route('webpush.unsubscribe', $subscription->id));

    $response->assertForbidden();

    $this->assertDatabaseHas('push_subscriptions', [
        'id' => $subscription->id,
    ]);
});
