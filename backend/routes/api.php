<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SalamPay API Routes
|--------------------------------------------------------------------------
|
| Base URL: /api/v1
| Authentication: Sanctum (user) or API Key (merchant)
|
*/

Route::prefix('v1')->group(function () {

    // =================================================================
    // PUBLIC ROUTES (No Authentication)
    // =================================================================

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'SalamPay API is running',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
        ]);
    });

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);
        Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
        Route::post('/login/otp', [\App\Http\Controllers\Api\V1\AuthController::class, 'requestOtp']);
        Route::post('/login/otp/verify', [\App\Http\Controllers\Api\V1\AuthController::class, 'verifyOtp']);
        Route::post('/password/reset', [\App\Http\Controllers\Api\V1\AuthController::class, 'requestPasswordReset']);
        Route::post('/password/reset/confirm', [\App\Http\Controllers\Api\V1\AuthController::class, 'confirmPasswordReset']);
    });

    // Provider webhooks (public but signature-verified)
    Route::prefix('webhooks')->group(function () {
        Route::post('/wave', [\App\Http\Controllers\Api\V1\WebhookController::class, 'wave']);
        Route::post('/orange-money', [\App\Http\Controllers\Api\V1\WebhookController::class, 'orangeMoney']);
        Route::post('/free-money', [\App\Http\Controllers\Api\V1\WebhookController::class, 'freeMoney']);
    });

    // =================================================================
    // PUBLIC CHECKOUT ROUTES (Guest Payments - No SalamPay Account Required)
    // =================================================================

    Route::prefix('checkout')->group(function () {
        // Get available payment providers
        Route::get('/providers', [\App\Http\Controllers\Api\V1\Public\CheckoutController::class, 'providers']);

        // Checkout session (from merchant API)
        Route::get('/sessions/{id}', [\App\Http\Controllers\Api\V1\Public\CheckoutController::class, 'resolve']);
        Route::post('/sessions/{id}/pay', [\App\Http\Controllers\Api\V1\Public\CheckoutController::class, 'pay']);

        // QR code payments
        Route::post('/qr/resolve', [\App\Http\Controllers\Api\V1\Public\CheckoutController::class, 'resolveQr']);
        Route::post('/qr/pay', [\App\Http\Controllers\Api\V1\Public\CheckoutController::class, 'payQr']);

        // Payment links
        Route::get('/links/{code}', [\App\Http\Controllers\Api\V1\Public\CheckoutController::class, 'resolvePaymentLink']);
        Route::post('/links/{code}/pay', [\App\Http\Controllers\Api\V1\Public\CheckoutController::class, 'payPaymentLink']);

        // Payment status (public, by reference)
        Route::get('/status/{reference}', [\App\Http\Controllers\Api\V1\Public\CheckoutController::class, 'status']);
    });

    // =================================================================
    // AUTHENTICATED USER ROUTES
    // =================================================================

    Route::middleware('auth:sanctum')->group(function () {

        // Auth management
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
            Route::post('/token/refresh', [\App\Http\Controllers\Api\V1\AuthController::class, 'refreshToken']);
            Route::post('/pin/set', [\App\Http\Controllers\Api\V1\AuthController::class, 'setPin']);
            Route::post('/pin/change', [\App\Http\Controllers\Api\V1\AuthController::class, 'changePin']);
        });

        // User profile
        Route::prefix('users')->group(function () {
            Route::get('/me', [\App\Http\Controllers\Api\V1\UserController::class, 'me']);
            Route::put('/me', [\App\Http\Controllers\Api\V1\UserController::class, 'update']);
            Route::get('/me/kyc', [\App\Http\Controllers\Api\V1\UserController::class, 'kycStatus']);
            Route::post('/me/kyc/documents', [\App\Http\Controllers\Api\V1\UserController::class, 'uploadKycDocument']);
        });

        // Wallets
        Route::prefix('wallets')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\WalletController::class, 'index']);
            Route::get('/{wallet}', [\App\Http\Controllers\Api\V1\WalletController::class, 'show']);
            Route::get('/{wallet}/balance', [\App\Http\Controllers\Api\V1\WalletController::class, 'balance']);
            Route::get('/{wallet}/transactions', [\App\Http\Controllers\Api\V1\WalletController::class, 'transactions']);
        });

        // Payments (Customer)
        Route::prefix('payments')->group(function () {
            // Deposits
            Route::get('/deposit/methods', [\App\Http\Controllers\Api\V1\PaymentController::class, 'depositMethods']);
            Route::post('/deposit/initiate', [\App\Http\Controllers\Api\V1\PaymentController::class, 'initiateDeposit']);
            Route::get('/deposit/{id}', [\App\Http\Controllers\Api\V1\PaymentController::class, 'depositStatus']);

            // Withdrawals
            Route::get('/withdraw/methods', [\App\Http\Controllers\Api\V1\PaymentController::class, 'withdrawMethods']);
            Route::post('/withdraw/initiate', [\App\Http\Controllers\Api\V1\PaymentController::class, 'initiateWithdraw']);
            Route::get('/withdraw/{id}', [\App\Http\Controllers\Api\V1\PaymentController::class, 'withdrawStatus']);

            // Transfers
            Route::post('/transfer', [\App\Http\Controllers\Api\V1\PaymentController::class, 'transfer']);
            Route::get('/transfer/{id}', [\App\Http\Controllers\Api\V1\PaymentController::class, 'transferStatus']);

            // QR Payments
            Route::post('/qr/scan', [\App\Http\Controllers\Api\V1\PaymentController::class, 'scanQr']);
            Route::post('/qr/pay', [\App\Http\Controllers\Api\V1\PaymentController::class, 'payQr']);
        });

        // Transactions history
        Route::get('/transactions', [\App\Http\Controllers\Api\V1\TransactionController::class, 'index']);
        Route::get('/transactions/{transaction}', [\App\Http\Controllers\Api\V1\TransactionController::class, 'show']);

    });

    // =================================================================
    // MERCHANT API ROUTES (API Key Authentication)
    // =================================================================

    Route::middleware('api.key')->prefix('merchant')->group(function () {

        // Checkout Sessions
        Route::prefix('checkout')->group(function () {
            Route::post('/sessions', [\App\Http\Controllers\Api\V1\Merchant\CheckoutController::class, 'create']);
            Route::get('/sessions/{id}', [\App\Http\Controllers\Api\V1\Merchant\CheckoutController::class, 'show']);
            Route::post('/sessions/{id}/cancel', [\App\Http\Controllers\Api\V1\Merchant\CheckoutController::class, 'cancel']);
            Route::post('/sessions/{id}/refund', [\App\Http\Controllers\Api\V1\Merchant\CheckoutController::class, 'refund']);
        });

        // QR Codes
        Route::apiResource('qr-codes', \App\Http\Controllers\Api\V1\Merchant\QrCodeController::class);

        // Payment Links
        Route::apiResource('payment-links', \App\Http\Controllers\Api\V1\Merchant\PaymentLinkController::class);

        // Invoices
        Route::apiResource('invoices', \App\Http\Controllers\Api\V1\Merchant\InvoiceController::class);
        Route::post('/invoices/{invoice}/send', [\App\Http\Controllers\Api\V1\Merchant\InvoiceController::class, 'send']);

        // Transactions
        Route::get('/transactions', [\App\Http\Controllers\Api\V1\Merchant\TransactionController::class, 'index']);
        Route::get('/transactions/{transaction}', [\App\Http\Controllers\Api\V1\Merchant\TransactionController::class, 'show']);

        // Settlements
        Route::get('/settlements', [\App\Http\Controllers\Api\V1\Merchant\SettlementController::class, 'index']);
        Route::get('/settlements/{settlement}', [\App\Http\Controllers\Api\V1\Merchant\SettlementController::class, 'show']);

        // Account
        Route::get('/account', [\App\Http\Controllers\Api\V1\Merchant\AccountController::class, 'show']);
        Route::get('/account/balance', [\App\Http\Controllers\Api\V1\Merchant\AccountController::class, 'balance']);

    });

});
