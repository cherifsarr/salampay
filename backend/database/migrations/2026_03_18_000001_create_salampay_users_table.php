<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend Laravel's default users table
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->string('phone', 20)->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->string('pin_hash')->nullable()->after('password');
            $table->enum('user_type', ['customer', 'merchant', 'agent', 'admin'])->default('customer');
            $table->enum('kyc_level', ['none', 'basic', 'verified', 'premium'])->default('none');
            $table->timestamp('kyc_verified_at')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'blocked'])->default('pending');
            $table->string('language', 5)->default('fr');
            $table->string('timezone', 50)->default('Africa/Dakar');
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();

            $table->index('phone');
            $table->index('user_type');
            $table->index('status');
        });

        // User profiles
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('nationality', 2)->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('SN');
            $table->string('profile_photo_url', 500)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // KYC documents
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->enum('document_type', ['national_id', 'passport', 'driver_license', 'selfie', 'proof_of_address']);
            $table->string('document_number', 100)->nullable();
            $table->string('document_url', 500);
            $table->date('expiry_date')->nullable();
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('user_profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'uuid', 'phone', 'phone_verified_at', 'pin_hash',
                'user_type', 'kyc_level', 'kyc_verified_at', 'status',
                'language', 'timezone', 'last_login_at', 'deleted_at'
            ]);
        });
    }
};
