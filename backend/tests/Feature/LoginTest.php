<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rejects_incorrect_credentials(): void
    {
        User::factory()->create([
            'email' => 'chong.wei.yang@example.com',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'chong.wei.yang@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }
}
