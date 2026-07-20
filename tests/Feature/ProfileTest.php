<?php

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('AC-PROFILE-1: updates the display name', function () {
    $user = User::factory()->create(['display_name' => 'Old Name']);

    $this->actingAs($user)
        ->patch('/profile', ['display_name' => 'New Name'])
        ->assertRedirect();

    expect($user->fresh()->display_name)->toBe('New Name');
});

it('AC-PROFILE-1: rejects an empty display name', function () {
    $user = User::factory()->create(['display_name' => 'Keep Me']);

    $this->actingAs($user)
        ->from('/profile')
        ->patch('/profile', ['display_name' => ''])
        ->assertSessionHasErrors('display_name');

    expect($user->fresh()->display_name)->toBe('Keep Me');
});

it('AC-PROFILE-2: changes the password when the current one is correct', function () {
    Notification::fake();
    $user = User::factory()->create(['password' => 'current-secret']);

    $this->actingAs($user)
        ->from('/profile')
        ->put('/profile/password', [
            'current_password' => 'current-secret',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])
        ->assertRedirect();

    expect(Hash::check('brand-new-secret', $user->fresh()->password))->toBeTrue();
    Notification::assertSentTo($user, PasswordChanged::class);
});

it('AC-PROFILE-2: rejects a wrong current password', function () {
    $user = User::factory()->create(['password' => 'current-secret']);

    $this->actingAs($user)
        ->from('/profile')
        ->put('/profile/password', [
            'current_password' => 'wrong-secret',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('current-secret', $user->fresh()->password))->toBeTrue();
});
