<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->setPin('1234');

        $this->wallet = Wallet::create([
            'uuid' => fake()->uuid(),
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'wallet_type' => 'main',
            'currency' => 'XOF',
            'balance' => 50000,
            'status' => 'active',
        ]);

        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_user_can_get_deposit_methods()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/payments/deposit/methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'methods' => [
                        '*' => [
                            'id',
                            'name',
                            'min_amount',
                            'max_amount',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_can_get_withdrawal_methods()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/payments/withdraw/methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'methods',
                ],
            ]);
    }

    public function test_user_can_transfer_to_another_user()
    {
        $recipient = User::factory()->create([
            'phone' => '+221779876543',
        ]);

        $recipientWallet = Wallet::create([
            'uuid' => fake()->uuid(),
            'owner_type' => User::class,
            'owner_id' => $recipient->id,
            'wallet_type' => 'main',
            'currency' => 'XOF',
            'balance' => 0,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/payments/transfer', [
                'amount' => 5000,
                'recipient_phone' => '+221779876543',
                'pin' => '1234',
                'description' => 'Test transfer',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Transfer successful',
            ])
            ->assertJsonStructure([
                'data' => [
                    'transaction_id',
                    'reference',
                    'amount',
                    'recipient',
                    'new_balance',
                ],
            ]);

        // Verify balances updated
        $this->wallet->refresh();
        $recipientWallet->refresh();

        $this->assertEquals(45000, $this->wallet->balance);
        $this->assertEquals(5000, $recipientWallet->balance);
    }

    public function test_transfer_fails_with_insufficient_balance()
    {
        $recipient = User::factory()->create([
            'phone' => '+221779876543',
        ]);

        Wallet::create([
            'uuid' => fake()->uuid(),
            'owner_type' => User::class,
            'owner_id' => $recipient->id,
            'wallet_type' => 'main',
            'currency' => 'XOF',
            'balance' => 0,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/payments/transfer', [
                'amount' => 100000,
                'recipient_phone' => '+221779876543',
                'pin' => '1234',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient balance',
            ]);
    }

    public function test_transfer_fails_with_wrong_pin()
    {
        $recipient = User::factory()->create([
            'phone' => '+221779876543',
        ]);

        Wallet::create([
            'uuid' => fake()->uuid(),
            'owner_type' => User::class,
            'owner_id' => $recipient->id,
            'wallet_type' => 'main',
            'currency' => 'XOF',
            'balance' => 0,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/payments/transfer', [
                'amount' => 5000,
                'recipient_phone' => '+221779876543',
                'pin' => '9999',
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid PIN',
            ]);
    }

    public function test_transfer_fails_to_self()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/payments/transfer', [
                'amount' => 5000,
                'recipient_phone' => $this->user->phone,
                'pin' => '1234',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot transfer to yourself',
            ]);
    }

    public function test_transfer_fails_to_nonexistent_user()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/payments/transfer', [
                'amount' => 5000,
                'recipient_phone' => '+221770000000',
                'pin' => '1234',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Recipient not found',
            ]);
    }

    public function test_user_can_scan_qr_code()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/payments/qr/scan', [
                'qr_data' => 'SP:abc123:5000',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'type' => 'salampay',
                    'uuid' => 'abc123',
                    'amount' => 5000,
                ],
            ]);
    }

    public function test_invalid_qr_code_rejected()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/payments/qr/scan', [
                'qr_data' => 'invalid-qr-data',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid QR code',
            ]);
    }
}
