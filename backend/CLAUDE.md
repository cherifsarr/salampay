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

#### Pending
- [ ] More provider adapters (FreeMoney, Wizall, E-Money)
- [ ] API tests
- [ ] Flutter apps (customer, merchant)
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
