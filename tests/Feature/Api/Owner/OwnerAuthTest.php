<?php

use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->owner = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();

    // Ensure known password for testing
    $this->owner->update(['password' => Hash::make('Secret123!')]);
    $this->cashier->update(['password' => Hash::make('Secret123!')]);
});

test('owner can login and receives 90-day token with owner:monitor ability', function () {
    $response = $this->postJson(route('api.v1.owner.login'), [
        'login'       => 'admin@alhusseini.com',
        'password'    => 'Secret123!',
        'device_name' => 'Ahmed iPhone 16 Pro',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ])
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ],
        ]);

    $tokenString = $response->json('data.token');
    expect($tokenString)->not->toBeEmpty();

    // Verify token exists in database with owner:monitor ability
    $tokenId = explode('|', $tokenString)[0];
    $tokenRecord = $this->owner->tokens()->where('id', $tokenId)->first();
    expect($tokenRecord)->not->toBeNull()
        ->and($tokenRecord->abilities)->toContain('owner:monitor');
});

test('login with incorrect password returns 422 validation error', function () {
    $response = $this->postJson(route('api.v1.owner.login'), [
        'login'    => 'admin@alhusseini.com',
        'password' => 'WrongPassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login']);
});

test('non-owner user cannot authenticate for owner mobile app and gets 403', function () {
    $response = $this->postJson(route('api.v1.owner.login'), [
        'login'    => 'cashier@alhusseini.com',
        'password' => 'Secret123!',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'code'   => 'OWNER_ROLE_REQUIRED',
        ]);
});

test('inactive owner account is rejected with 403', function () {
    $this->owner->update(['is_active' => false]);

    $response = $this->postJson(route('api.v1.owner.login'), [
        'login'    => 'admin@alhusseini.com',
        'password' => 'Secret123!',
    ]);

    $response->assertStatus(403);
});

test('unauthenticated request to protected owner endpoints returns 401 JSON', function () {
    $response = $this->getJson(route('api.v1.owner.me'));
    $response->assertStatus(401);
});

test('token lacking owner:monitor ability is forbidden with 403', function () {
    $token = $this->owner->createToken('OtherToken', ['read:general'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson(route('api.v1.owner.me'));

    $response->assertStatus(403);
});

test('me endpoint returns owner profile with valid token', function () {
    $token = $this->owner->createToken('Device', ['owner:monitor'])->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson(route('api.v1.owner.me'));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data'   => [
                'id'    => $this->owner->id,
                'email' => 'admin@alhusseini.com',
            ],
        ]);
});

test('device-token endpoint registers push tokens idempotently', function () {
    $token = $this->owner->createToken('Device', ['owner:monitor'])->plainTextToken;

    $postData = [
        'device_token' => 'apns-test-token-xyz-12345',
        'platform'     => 'ios',
        'device_name'  => 'Owner iPhone',
    ];

    // First post
    $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson(route('api.v1.owner.device_token'), $postData)
        ->assertOk();

    expect(UserDevice::where('user_id', $this->owner->id)->count())->toBe(1);

    // Second post with same token must not create duplicates
    $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson(route('api.v1.owner.device_token'), $postData)
        ->assertOk();

    expect(UserDevice::where('user_id', $this->owner->id)->count())->toBe(1);
});

test('logout revokes only current token and leaves other device tokens intact', function () {
    $token1 = $this->owner->createToken('iPhone', ['owner:monitor'])->plainTextToken;
    $token2 = $this->owner->createToken('iPad', ['owner:monitor'])->plainTextToken;

    expect($this->owner->tokens()->count())->toBe(2);

    $this->withHeader('Authorization', 'Bearer ' . $token1)
        ->postJson(route('api.v1.owner.logout'))
        ->assertOk();

    expect($this->owner->tokens()->count())->toBe(1)
        ->and($this->owner->tokens()->where('name', 'iPad')->exists())->toBeTrue()
        ->and($this->owner->tokens()->where('name', 'iPhone')->exists())->toBeFalse();

    app('auth')->forgetGuards();

    // Token 2 should still be valid
    $this->withHeader('Authorization', 'Bearer ' . $token2)
        ->getJson(route('api.v1.owner.me'))
        ->assertOk();

    app('auth')->forgetGuards();

    // Token 1 should no longer be valid
    $this->withHeader('Authorization', 'Bearer ' . $token1)
        ->getJson(route('api.v1.owner.me'))
        ->assertStatus(401);
});
