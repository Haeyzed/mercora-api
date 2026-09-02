<?php

use App\Models\Landlord\User;
use App\Notifications\Landlord\ResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

describe('login', function () {
    it('issues a token for valid landlord credentials', function () {
        $user = User::factory()->create([
            'email' => 'admin@mercora.test',
        ]);

        $this->postJson('/api/landlord/auth/login', [
            'email' => 'admin@mercora.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'admin@mercora.test')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonMissingPath('data.user.password');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    });

    it('returns 422 when the user is inactive', function () {
        User::factory()->inactive()->create([
            'email' => 'admin@mercora.test',
        ]);

        $this->postJson('/api/landlord/auth/login', [
            'email' => 'admin@mercora.test',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('returns 422 when the credentials are incorrect', function () {
        User::factory()->create([
            'email' => 'admin@mercora.test',
        ]);

        $this->postJson('/api/landlord/auth/login', [
            'email' => 'admin@mercora.test',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('returns 422 when required login fields are missing', function () {
        $this->postJson('/api/landlord/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });
});

describe('forgotPassword', function () {
    it('returns no content without revealing whether the email exists', function () {
        Notification::fake();

        $this->postJson('/api/landlord/auth/forgot-password', [
            'email' => 'missing@mercora.test',
        ])->assertNoContent();

        Notification::assertNothingSent();
    });

    it('sends a reset notification for an active user', function () {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@mercora.test']);

        $this->postJson('/api/landlord/auth/forgot-password', [
            'email' => 'reset@mercora.test',
        ])->assertNoContent();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    });

    it('does not send a reset notification for inactive users', function () {
        Notification::fake();

        User::factory()->inactive()->create(['email' => 'reset@mercora.test']);

        $this->postJson('/api/landlord/auth/forgot-password', [
            'email' => 'reset@mercora.test',
        ])->assertNoContent();

        Notification::assertNothingSent();
    });
});

describe('resetPassword', function () {
    it('resets the password and revokes existing tokens', function () {
        $user = User::factory()->create(['email' => 'reset@mercora.test']);
        $user->createToken('landlord');
        $token = Password::broker('users')->createToken($user);

        $this->postJson('/api/landlord/auth/reset-password', [
            'email' => 'reset@mercora.test',
            'token' => $token,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertNoContent();

        expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('returns 422 for an invalid token', function () {
        User::factory()->create(['email' => 'reset@mercora.test']);

        $this->postJson('/api/landlord/auth/reset-password', [
            'email' => 'reset@mercora.test',
            'token' => 'invalid-token',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
});

describe('me', function () {
    it('returns the authenticated landlord user', function () {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@mercora.test',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/landlord/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada@mercora.test')
            ->assertJsonMissingPath('data.password');
    });

    it('returns 401 when no token is provided', function () {
        $this->getJson('/api/landlord/auth/me')
            ->assertUnauthorized();
    });
});

describe('updateProfile', function () {
    it('updates profile fields and optional avatar', function () {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@mercora.test',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/landlord/auth/profile', [
            'name' => 'Grace Hopper',
            'email' => 'grace@mercora.test',
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Grace Hopper')
            ->assertJsonPath('data.email', 'grace@mercora.test');

        expect($user->fresh()->getFirstMedia('avatar'))->not->toBeNull();
    });
});

describe('avatar', function () {
    it('replaces the authenticated user avatar', function () {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/landlord/auth/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.collection', 'avatar');

        expect($user->fresh()->getFirstMedia('avatar'))->not->toBeNull();
    });

    it('removes the authenticated user avatar', function () {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))->toMediaCollection('avatar');

        $this->deleteJson('/api/landlord/auth/avatar')
            ->assertNoContent();

        expect($user->fresh()->getFirstMedia('avatar'))->toBeNull();
    });
});

describe('changePassword', function () {
    it('changes the password and revokes all tokens', function () {
        $user = User::factory()->create(['password' => 'password']);
        $user->createToken('landlord');
        Sanctum::actingAs($user);

        $this->postJson('/api/landlord/auth/change-password', [
            'current_password' => 'password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertNoContent();

        expect(Hash::check('NewPassword1!', $user->fresh()->password))->toBeTrue();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('returns 422 when the current password is incorrect', function () {
        $user = User::factory()->create(['password' => 'password']);
        Sanctum::actingAs($user);

        $this->postJson('/api/landlord/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    });
});

describe('logout', function () {
    it('revokes the current landlord token', function () {
        User::factory()->create([
            'email' => 'admin@mercora.test',
        ]);

        $token = $this->postJson('/api/landlord/auth/login', [
            'email' => 'admin@mercora.test',
            'password' => 'password',
        ])->json('data.token');

        $this->withToken($token)
            ->postJson('/api/landlord/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('returns 401 when no token is provided', function () {
        $this->postJson('/api/landlord/auth/logout')
            ->assertUnauthorized();
    });
});

describe('protected landlord routes', function () {
    it('returns 401 when a world route is requested without a token', function () {
        $this->getJson('/api/landlord/world/countries')
            ->assertUnauthorized();
    });

    it('returns 401 when a tenant route is requested without a token', function () {
        $this->getJson('/api/landlord/tenants')
            ->assertUnauthorized();
    });

    it('returns 401 when a plan route is requested without a token', function () {
        $this->getJson('/api/landlord/plans')
            ->assertUnauthorized();
    });

    it('returns 401 when a subscription route is requested without a token', function () {
        $this->getJson('/api/landlord/subscriptions')
            ->assertUnauthorized();
    });

    it('returns 401 when an invoice route is requested without a token', function () {
        $this->getJson('/api/landlord/invoices')
            ->assertUnauthorized();
    });

    it('returns 401 when a setting route is requested without a token', function () {
        $this->getJson('/api/landlord/settings')
            ->assertUnauthorized();
    });

    it('returns 401 when a notification route is requested without a token', function () {
        $this->getJson('/api/landlord/notifications')
            ->assertUnauthorized();
    });

    it('returns 401 when an API key route is requested without a token', function () {
        $this->getJson('/api/landlord/api-keys')
            ->assertUnauthorized();
    });

    it('returns 401 when an activity route is requested without a token', function () {
        $this->getJson('/api/landlord/activities')
            ->assertUnauthorized();
    });
});
