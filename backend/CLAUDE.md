# SalamPay Payment Gateway Platform

## Overview

SalamPay is an independent payment gateway platform for Senegal, aggregating all local mobile money providers (Wave, Orange Money, Free Money, Wizall, E-Money) and card payments into a unified platform. Similar to PayPal, it enables wallet-based transactions, P2P transfers, and comprehensive merchant services.

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        SALAMPAY PLATFORM                         │
├─────────────────────────────────────────────────────────────────┤
│  Customer App     Merchant App      Admin Dashboard              │
│   (Flutter)        (Flutter)        (Next.js)                    │
│      │                │                   │                      │
│      └────────────────┼───────────────────┘                      │
│                       ▼                                          │
│              ┌─────────────────┐                                 │
│              │   API Gateway   │                                 │
│              └────────┬────────┘                                 │
│                       │                                          │
│   ┌───────────┬───────┼───────┬───────────┬───────────┐         │
│   ▼           ▼       ▼       ▼           ▼           ▼         │
│ Identity   Wallet  Payment  Merchant  Settlement   Provider     │
│ Module     Module  Module   Module    Module       Module       │
│                       │                                          │
│              ┌────────┴────────┐                                 │
│              │ Provider Layer  │                                 │
│              └────────┬────────┘                                 │
│   ┌─────┬─────┬───────┼───────┬─────┬─────┐                     │
│   ▼     ▼     ▼       ▼       ▼     ▼     ▼                     │
│ Wave  Orange  Free  Wizall  E-Money  Visa  Banks                │
└─────────────────────────────────────────────────────────────────┘
```

## Repository Structure

```
SalamPay/
├── backend/                    # Laravel API (this project)
│   ├── app/
│   │   ├── Modules/
│   │   │   ├── Identity/       # Auth, KYC, Users
│   │   │   ├── Wallet/         # Wallet management
│   │   │   ├── Payment/        # Transactions, Checkout
│   │   │   ├── Merchant/       # Merchant services
│   │   │   ├── Settlement/     # Payouts, Reconciliation
│   │   │   └── Provider/       # Payment provider adapters
│   │   │       └── Adapters/
│   │   │           ├── Wave/
│   │   │           ├── OrangeMoney/
│   │   │           ├── FreeMoney/
│   │   │           ├── Wizall/
│   │   │           └── EMoney/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── Api/V1/
│   │   │   └── Middleware/
│   │   └── Models/
│   ├── config/
│   ├── database/
│   │   └── migrations/
│   └── routes/
│       └── api.php
│
├── apps/
│   ├── customer/               # Flutter customer app
│   └── merchant/               # Flutter merchant app
│
└── admin/                      # Next.js admin dashboard
```

## Modules

### Identity Module
- User registration (phone-based)
- OTP authentication
- KYC document management
- User profiles

### Wallet Module
- Multi-currency wallets
- Balance management
- Transaction limits
- Double-entry ledger

### Payment Module
- Checkout sessions
- P2P transfers
- Bill payments
- QR code payments
- Payment links

### Merchant Module
- Merchant onboarding (KYB)
- Store management
- QR code generation
- Invoice creation
- API key management

### Settlement Module
- Batch settlements
- Reconciliation
- Payout processing

### Provider Module
- Payment provider abstraction layer
- Adapters for each provider:
  - Wave
  - Orange Money
  - Free Money
  - Wizall
  - E-Money
  - Card payments (Visa/Mastercard)

## Database

Using PostgreSQL for better JSON support and ACID compliance.

### Key Tables
- `users` - All user accounts
- `wallets` - User/merchant wallets
- `transactions` - All financial transactions
- `ledger_entries` - Double-entry bookkeeping
- `merchants` - Merchant accounts
- `merchant_stores` - Physical store locations
- `provider_accounts` - Payment provider credentials
- `qr_codes` - Static/dynamic QR codes
- `payment_links` - Payment link records
- `invoices` - Merchant invoices
- `settlement_batches` - Settlement records
- `api_keys` - Developer API keys

## API Versioning

Base URL: `https://api.salampay.sn/v1`

### Authentication
- OAuth 2.0 for user authentication
- API Keys for merchant integration
- Request signing (HMAC-SHA256)

## Environment Variables

```env
APP_NAME=SalamPay
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=salampay
DB_USERNAME=salampay
DB_PASSWORD=secret

# Provider API Keys (encrypted)
WAVE_API_KEY=
WAVE_WEBHOOK_SECRET=
ORANGE_MONEY_API_KEY=
FREE_MONEY_API_KEY=

# Security
JWT_SECRET=
ENCRYPTION_KEY=
```

## Git Workflow

Same as Salam Ticket - feature branches, PRs to main, no direct pushes.

```bash
git checkout -b feature/your-feature
git commit -m "feat: description"
git push origin feature/your-feature
# Create PR on GitHub
```

## Implementation Progress

### Phase 1: Foundation (Current)

#### Completed
- [x] Project setup (Laravel 12)
- [x] Database migrations (users, wallets, transactions, merchants, providers)
- [x] Payment provider interface (`PaymentProviderInterface`)
- [x] Wave adapter implementation (checkout, payout, refund, webhooks)
- [x] Orange Money adapter implementation (OAuth2, checkout, payout)
- [x] Provider factory for managing adapters
- [x] Core Eloquent models:
  - `User` - Extended with phone auth, KYC, PIN
  - `UserProfile` - Profile details
  - `Wallet` - With credit/debit/hold operations
  - `WalletHold` - Temporary balance holds
  - `Transaction` - Full transaction schema
  - `LedgerEntry` - Double-entry bookkeeping
  - `Merchant` - Business accounts
  - `MerchantStore` - Physical locations
  - `ApiKey` - Developer API keys with signing
  - `KycDocument` - KYC document management
  - `QrCode` - Static/dynamic QR codes
  - `PaymentLink` - Payment link records
  - `Invoice` - Merchant invoices
  - `SettlementBatch` - Settlement records
- [x] API routes structure (v1) - auth, wallets, payments, merchant API
- [x] API key authentication middleware
- [x] Payment provider config (Wave, Orange Money, Free Money, Wizall, E-Money)
- [x] Identity module controllers:
  - `AuthController` - Register, login, OTP, password reset, PIN management
  - `UserController` - Profile management, KYC document upload
- [x] Payment module controllers:
  - `PaymentController` - Deposits, withdrawals, P2P transfers, QR payments
  - `TransactionController` - Transaction history
  - `WalletController` - Wallet management
  - `WebhookController` - Wave, Orange Money webhooks
- [x] Merchant API controllers:
  - `CheckoutController` - Checkout sessions
  - `QrCodeController` - QR code management
  - `PaymentLinkController` - Payment links
  - `InvoiceController` - Invoice management
  - `TransactionController` - Merchant transactions
  - `SettlementController` - Settlement batches
  - `AccountController` - Merchant account info

#### Completed (continued)
- [x] Free Money adapter implementation (checkout, payout, refund, webhooks)
- [x] Wizall adapter implementation (checkout, payout, refund, webhooks)
- [x] E-Money adapter implementation (checkout, payout, refund, webhooks)
- [x] API tests (26 tests passing)
  - AuthTest (11 tests) - Registration, login, OTP, password reset
  - WalletTest (6 tests) - Balance, holds, transactions
  - PaymentTest (9 tests) - Deposits, withdrawals, transfers

#### Completed (Flutter Customer App)
- [x] Flutter customer app project setup with GetX architecture
- [x] Theme configuration with custom colors
- [x] Data models (User, Wallet, Transaction)
- [x] API service with Dio and secure storage
- [x] Auth controller with full authentication flow
- [x] Home controller and wallet controller
- [x] Payment controller for deposits, withdrawals, transfers
- [x] Authentication views (Login, Register, OTP, PIN setup)
- [x] Home view with wallet card and quick actions
- [x] Wallet view with transaction history
- [x] Payment views (Deposit, Transfer, Withdraw, QR Scan)
- [x] Settings view with profile and security options
- [x] App routing with GetX navigation

#### Completed (Flutter Merchant App)
- [x] Flutter merchant app project setup with GetX architecture
- [x] Theme configuration with business blue colors
- [x] Data models (Merchant, QrCode, PaymentLink, Transaction, Settlement)
- [x] API service for merchant endpoints
- [x] Auth controller with email/password login
- [x] Dashboard controller with stats and recent transactions
- [x] QR code controller with create/delete functionality
- [x] Payment link controller for link management
- [x] Transaction controller with filtering and refunds
- [x] Settlement controller with payout requests
- [x] Login view for merchants
- [x] Dashboard view with sales stats and quick actions
- [x] QR code management views (list, create, detail)
- [x] Payment link management views (list, create)
- [x] Transaction list view with details
- [x] Settlement list view with payout requests
- [x] Settings view with business profile
- [x] POS (Point of Sale) system:
  - Numeric keypad for amount entry
  - Dynamic QR code generation for payments
  - Client display view for secondary screens
  - Real-time payment status polling
  - Success/failure screens

#### Pending
- [ ] Admin dashboard (Next.js)

### Phase 2: Core Features
- [ ] User registration & OTP auth
- [ ] Wallet top-up (Wave)
- [ ] P2P transfers
- [ ] Merchant onboarding

### Phase 3: Advanced Features
- [ ] QR code payments
- [ ] Payment links
- [ ] Invoicing
- [ ] Settlements

## Related Projects

- **Salam Ticket** (`C:\Projects\CROUS\backendv2`) - University services platform, will integrate with SalamPay for payments
