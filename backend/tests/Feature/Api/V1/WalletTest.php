<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = Wallet::create([
            'uuid' => fake()->uuid(),
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'wallet_type' => 'main',
            'currency' => 'XOF',
            'balance' => 10000,
            'status' => 'active',
        ]);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_user_can_list_wallets()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/wallets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'wallets' => [
                        '*' => [
                            'uuid',
                            'wallet_type',
                            'currency',
                            'balance',
                            'available_balance',
                            'status',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_view_wallet()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/wallets/{$this->wallet->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'wallet' => [
                        'uuid' => $this->wallet->uuid,
                        'currency' => 'XOF',
                        'balance' => 10000,
                    ],
                ],
            ]);
    }

    public function test_user_can_view_wallet_balance()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/wallets/{$this->wallet->id}/balance");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 10000,
                    'currency' => 'XOF',
                ],
            ]);
    }

    public function test_user_cannot_view_other_users_wallet()
    {
        $otherUser = User::factory()->create();
        $otherWallet = Wallet::create([
            'uuid' => fake()->uuid(),
            'owner_type' => User::class,
            'owner_id' => $otherUser->id,
            'wallet_type' => 'main',
            'currency' => 'XOF',
            'balance' => 5000,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/wallets/{$otherWallet->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_view_wallet_transactions()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/wallets/{$this->wallet->id}/transactions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transactions',
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_wallets()
    {
        $response = $this->getJson('/api/v1/wallets');

        $response->assertStatus(401);
    }
}
