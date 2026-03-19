<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^(\+221|221)?[0-9]{9}$/', 'unique:users,phone'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'user_type' => ['sometimes', 'in:customer,merchant'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Normalize phone number to international format
        $phone = $this->normalizePhone($request->phone);

        $user = User::create([
            'uuid' => Str::uuid(),
            'name' => $request->name,
            'phone' => $phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => $request->input('user_type', 'customer'),
            'kyc_level' => 'none',
            'status' => 'pending_verification',
            'language' => $request->input('language', 'fr'),
        ]);

        // Create main wallet for user
        $wallet = Wallet::create([
            'uuid' => Str::uuid(),
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'wallet_type' => 'main',
            'currency' => 'XOF',
            'balance' => 0,
            'status' => 'active',
        ]);

        // Generate OTP for phone verification
        $otp = $this->generateOtp($phone);

        // TODO: Send OTP via SMS gateway
        // $this->sendSms($phone, "Votre code SalamPay: {$otp}");

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please verify your phone number.',
            'data' => [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                ],
                'requires_phone_verification' => true,
            ],
        ], 201);
    }

    /**
     * Login with phone and password
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);
        $user = User::where('phone', $phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if ($user->status === 'blocked') {
            return response()->json([
                'success' => false,
                'message' => 'Account is blocked. Please contact support.',
            ], 403);
        }

        if ($user->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Account is suspended. Please contact support.',
            ], 403);
        }

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Create Sanctum token
        $deviceName = $request->input('device_name', 'mobile');
        $token = $user->createToken($deviceName, ['*'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Request OTP for phone login
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^(\+221|221)?[0-9]{9}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            // Don't reveal if user exists or not
            return response()->json([
                'success' => true,
                'message' => 'If this phone number is registered, you will receive an OTP.',
            ]);
        }

        // Rate limiting: max 3 OTP requests per hour
        $rateLimitKey = "otp_limit:{$phone}";
        $attempts = Cache::get($rateLimitKey, 0);

        if ($attempts >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Too many OTP requests. Please try again later.',
            ], 429);
        }

        Cache::put($rateLimitKey, $attempts + 1, 3600);

        // Generate and store OTP
        $otp = $this->generateOtp($phone);

        // TODO: Send OTP via SMS gateway
        // $this->sendSms($phone, "Votre code SalamPay: {$otp}");

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'data' => [
                'expires_in' => 300, // 5 minutes
            ],
        ]);
    }

    /**
     * Verify OTP and login
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        // Verify OTP
        if (!$this->verifyStoredOtp($phone, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ], 401);
        }

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Mark phone as verified if not already
        if (!$user->phone_verified_at) {
            $user->update([
                'phone_verified_at' => now(),
                'status' => 'active',
            ]);
        }

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Create token
        $deviceName = $request->input('device_name', 'mobile');
        $token = $user->createToken($deviceName, ['*'])->plainTextToken;

        // Clear OTP
        Cache::forget("otp:{$phone}");

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Request password reset
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^(\+221|221)?[0-9]{9}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);
        $user = User::where('phone', $phone)->first();

        if ($user) {
            $otp = $this->generateOtp($phone, 'reset');

            // TODO: Send OTP via SMS gateway
            // $this->sendSms($phone, "Code de réinitialisation SalamPay: {$otp}");
        }

        // Don't reveal if user exists
        return response()->json([
            'success' => true,
            'message' => 'If this phone number is registered, you will receive a reset code.',
        ]);
    }

    /**
     * Confirm password reset
     */
    public function confirmPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        // Verify OTP
        if (!$this->verifyStoredOtp($phone, $request->otp, 'reset')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset code',
            ], 401);
        }

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all existing tokens
        $user->tokens()->delete();

        // Clear OTP
        Cache::forget("otp:reset:{$phone}");

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful. Please login with your new password.',
        ]);
    }

    /**
     * Logout (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Refresh token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceName = $request->input('device_name', 'mobile');

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken($deviceName, ['*'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Set PIN for transactions
     */
    public function setPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if ($user->pin_hash) {
            return response()->json([
                'success' => false,
                'message' => 'PIN already set. Use change PIN endpoint.',
            ], 400);
        }

        $user->setPin($request->pin);

        return response()->json([
            'success' => true,
            'message' => 'PIN set successfully',
        ]);
    }

    /**
     * Change PIN
     */
    public function changePin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_pin' => ['required', 'string', 'size:4'],
            'new_pin' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
            'new_pin_confirmation' => ['required', 'same:new_pin'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!$user->verifyPin($request->current_pin)) {
            return response()->json([
                'success' => false,
                'message' => 'Current PIN is incorrect',
            ], 401);
        }

        $user->setPin($request->new_pin);

        return response()->json([
            'success' => true,
            'message' => 'PIN changed successfully',
        ]);
    }

    // Helper methods

    private function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove country code if present
        if (str_starts_with($phone, '221')) {
            $phone = substr($phone, 3);
        }

        // Add country code
        return '+221' . $phone;
    }

    private function generateOtp(string $phone, string $type = 'login'): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $key = $type === 'reset' ? "otp:reset:{$phone}" : "otp:{$phone}";
        Cache::put($key, [
            'otp' => $otp,
            'attempts' => 0,
        ], 300); // 5 minutes

        return $otp;
    }

    private function verifyStoredOtp(string $phone, string $otp, string $type = 'login'): bool
    {
        $key = $type === 'reset' ? "otp:reset:{$phone}" : "otp:{$phone}";
        $stored = Cache::get($key);

        if (!$stored) {
            return false;
        }

        // Max 3 attempts
        if ($stored['attempts'] >= 3) {
            Cache::forget($key);
            return false;
        }

        if ($stored['otp'] !== $otp) {
            Cache::put($key, [
                'otp' => $stored['otp'],
                'attempts' => $stored['attempts'] + 1,
            ], 300);
            return false;
        }

        return true;
    }

    private function formatUser(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'kyc_level' => $user->kyc_level,
            'status' => $user->status,
            'phone_verified' => (bool) $user->phone_verified_at,
            'email_verified' => (bool) $user->email_verified_at,
            'has_pin' => (bool) $user->pin_hash,
            'balance' => $user->getBalance(),
            'created_at' => $user->created_at->toISOString(),
        ];
    }
}
