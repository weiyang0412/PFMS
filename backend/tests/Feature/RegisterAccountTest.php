<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_account_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Chong Wei Yang',
            'email' => 'not-an-email',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
        $response->assertJsonPath('errors.email.0', 'Please enter a valid email address.');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_account_rejects_weak_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Chong Wei Yang',
            'email' => 'chong.wei.yang@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }
}
