<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get current user profile
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['profile', 'mainWallet']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUserFull($user),
            ],
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $request->user()->id],
            'language' => ['sometimes', 'string', 'in:fr,en,wo'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            // Profile fields
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'gender' => ['sometimes', 'in:male,female,other'],
            'address_line1' => ['sometimes', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:100'],
            'region' => ['sometimes', 'string', 'max:100'],
            'country' => ['sometimes', 'string', 'size:2'],
            'postal_code' => ['sometimes', 'string', 'max:20'],
            'avatar' => ['sometimes', 'image', 'max:2048'], // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Update user fields
        $userFields = ['name', 'email', 'language', 'timezone'];
        $user->fill($request->only($userFields));
        $user->save();

        // Update or create profile
        $profileFields = [
            'first_name', 'last_name', 'date_of_birth', 'gender',
            'address_line1', 'address_line2', 'city', 'region',
            'country', 'postal_code',
        ];

        $profileData = $request->only($profileFields);

        if (!empty($profileData) || $request->hasFile('avatar')) {
            $profile = $user->profile ?? new UserProfile(['user_id' => $user->id]);

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                // Delete old avatar
                if ($profile->profile_photo_url) {
                    Storage::disk('public')->delete($profile->profile_photo_url);
                }

                $path = $request->file('avatar')->store('avatars', 'public');
                $profileData['profile_photo_url'] = $path;
            }

            $profile->fill($profileData);
            $profile->save();
        }

        $user->load(['profile', 'mainWallet']);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $this->formatUserFull($user),
            ],
        ]);
    }

    /**
     * Get KYC status
     */
    public function kycStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('kycDocuments');

        $documents = $user->kycDocuments->map(function ($doc) {
            return [
                'id' => $doc->id,
                'type' => $doc->document_type,
                'status' => $doc->status,
                'rejection_reason' => $doc->rejection_reason,
                'submitted_at' => $doc->created_at->toISOString(),
                'reviewed_at' => $doc->reviewed_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'kyc_level' => $user->kyc_level,
                'kyc_verified_at' => $user->kyc_verified_at?->toISOString(),
                'documents' => $documents,
                'requirements' => $this->getKycRequirements($user->kyc_level),
            ],
        ]);
    }

    /**
     * Upload KYC document
     */
    public function uploadKycDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type' => ['required', 'in:national_id,passport,driver_license,selfie,proof_of_address'],
            'document_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // 5MB max
            'document_number' => ['sometimes', 'string', 'max:50'],
            'expiry_date' => ['sometimes', 'date', 'after:today'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Check if document type already submitted and pending
        $existing = $user->kycDocuments()
            ->where('document_type', $request->document_type)
            ->whereIn('verification_status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            $status = $existing->verification_status;
            return response()->json([
                'success' => false,
                'message' => "A {$request->document_type} document is already {$status}",
            ], 400);
        }

        // Store document
        $path = $request->file('document_file')->store('kyc-documents', 'private');

        $document = $user->kycDocuments()->create([
            'document_type' => $request->document_type,
            'document_url' => $path,
            'document_number' => $request->document_number,
            'expiry_date' => $request->expiry_date,
            'verification_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully. It will be reviewed shortly.',
            'data' => [
                'document' => [
                    'id' => $document->id,
                    'type' => $document->document_type,
                    'status' => $document->status,
                    'submitted_at' => $document->created_at->toISOString(),
                ],
            ],
        ], 201);
    }

    // Helper methods

    private function formatUserFull($user): array
    {
        $data = [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'kyc_level' => $user->kyc_level,
            'status' => $user->status,
            'language' => $user->language,
            'timezone' => $user->timezone,
            'phone_verified' => (bool) $user->phone_verified_at,
            'email_verified' => (bool) $user->email_verified_at,
            'has_pin' => (bool) $user->pin_hash,
            'created_at' => $user->created_at->toISOString(),
        ];

        // Add wallet info
        if ($user->mainWallet) {
            $data['wallet'] = [
                'uuid' => $user->mainWallet->uuid,
                'balance' => (float) $user->mainWallet->balance,
                'currency' => $user->mainWallet->currency,
                'status' => $user->mainWallet->status,
            ];
        }

        // Add profile info
        if ($user->profile) {
            $data['profile'] = [
                'first_name' => $user->profile->first_name,
                'last_name' => $user->profile->last_name,
                'date_of_birth' => $user->profile->date_of_birth?->format('Y-m-d'),
                'gender' => $user->profile->gender,
                'address_line1' => $user->profile->address_line1,
                'address_line2' => $user->profile->address_line2,
                'city' => $user->profile->city,
                'region' => $user->profile->region,
                'country' => $user->profile->country,
                'postal_code' => $user->profile->postal_code,
                'avatar_url' => $user->profile->profile_photo_url
                    ? Storage::disk('public')->url($user->profile->profile_photo_url)
                    : null,
            ];
        }

        return $data;
    }

    private function getKycRequirements(string $currentLevel): array
    {
        $requirements = [
            'none' => [
                'next_level' => 'basic',
                'documents_required' => ['national_id', 'selfie'],
                'benefits' => [
                    'Increased transaction limits',
                    'Access to all payment methods',
                ],
            ],
            'basic' => [
                'next_level' => 'verified',
                'documents_required' => ['proof_of_address'],
                'benefits' => [
                    'Higher withdrawal limits',
                    'Merchant account eligibility',
                ],
            ],
            'verified' => [
                'next_level' => 'premium',
                'documents_required' => [],
                'benefits' => [
                    'Maximum limits',
                    'Priority support',
                ],
                'note' => 'Premium verification requires manual review',
            ],
            'premium' => [
                'next_level' => null,
                'documents_required' => [],
                'benefits' => ['You have the highest verification level'],
            ],
        ];

        return $requirements[$currentLevel] ?? $requirements['none'];
    }
}
