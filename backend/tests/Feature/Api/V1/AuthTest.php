<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '+221771234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'uuid',
                        'name',
                        'phone',
                    ],
                    'requires_phone_verification',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'requires_phone_verification' => true,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '+221771234567',
            'name' => 'Test User',
        ]);
    }

    public function test_registration_fails_with_invalid_phone()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '12345',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_registration_fails_with_duplicate_phone()
    {
        User::factory()->create(['phone' => '+221771234567']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '+221771234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_user_can_login_with_password()
    {
        $user = User::factory()->create([
            'phone' => '+221771234567',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+221771234567',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'token',
                    'token_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                ],
            ]);
    }

    public function test_login_fails_with_wrong_password()
    {
        User::factory()->create([
            'phone' => '+221771234567',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+221771234567',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_blocked_user_cannot_login()
    {
        User::factory()->create([
            'phone' => '+221771234567',
            'password' => Hash::make('password123'),
            'status' => 'blocked',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+221771234567',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
    }

    public function test_user_can_set_pin()
    {
        $user = User::factory()->create(['pin_hash' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/pin/set', [
                'pin' => '1234',
                'pin_confirmation' => '1234',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PIN set successfully',
            ]);

        $user->refresh();
        $this->assertTrue($user->verifyPin('1234'));
    }

    public function test_user_cannot_set_pin_if_already_set()
    {
        $user = User::factory()->create();
        $user->setPin('1234');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/pin/set', [
                'pin' => '5678',
                'pin_confirmation' => '5678',
            ]);

        $response->assertStatus(400);
    }

    public function test_user_can_change_pin()
    {
        $user = User::factory()->create();
        $user->setPin('1234');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/pin/change', [
                'current_pin' => '1234',
                'new_pin' => '5678',
                'new_pin_confirmation' => '5678',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PIN changed successfully',
            ]);

        $user->refresh();
        $this->assertTrue($user->verifyPin('5678'));
    }

    public function test_health_check_returns_ok()
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'SalamPay API is running',
                'version' => '1.0.0',
            ]);
    }
}
