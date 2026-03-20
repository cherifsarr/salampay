<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalamPay API Documentation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        primary: { 50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857', 800: '#065f46', 900: '#064e3b' },
                        dark: { 900: '#0f172a', 800: '#1e293b', 700: '#334155', 600: '#475569' }
                    }
                }
            }
        }
    </script>
    <style>
        .code-block { background: #1e293b; }
        .sidebar-link.active { background: rgba(16, 185, 129, 0.1); border-left-color: #10b981; color: #10b981; }
        .method-get { background: #22c55e; }
        .method-post { background: #3b82f6; }
        .method-put { background: #f59e0b; }
        .method-delete { background: #ef4444; }
        pre code { font-size: 13px; line-height: 1.6; }
        .copy-btn:hover { opacity: 1; }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">
    <!-- Header -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-white border-b border-gray-200">
        <div class="flex items-center justify-between h-16 px-6">
            <div class="flex items-center space-x-8">
                <a href="/" class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span class="text-lg font-bold">SalamPay <span class="text-primary-500">API</span></span>
                </a>
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="#introduction" class="text-gray-600 hover:text-primary-600 font-medium">Guides</a>
                    <a href="#authentication" class="text-gray-600 hover:text-primary-600 font-medium">Reference</a>
                    <a href="#sdks" class="text-gray-600 hover:text-primary-600 font-medium">SDKs</a>
                </nav>
            </div>
            <div class="flex items-center space-x-4">
                <span class="hidden md:inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary-100 text-primary-700">v1.0</span>
                <a href="/register" class="bg-primary-500 hover:bg-primary-600 text-white px-4 py-2 rounded-lg font-medium text-sm transition">Get API Keys</a>
            </div>
        </div>
    </header>

    <div class="flex pt-16">
        <!-- Sidebar -->
        <aside class="fixed left-0 top-16 bottom-0 w-64 bg-white border-r border-gray-200 overflow-y-auto">
            <nav class="p-4 space-y-1">
                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-3">Getting Started</div>
                <a href="#introduction" class="sidebar-link active block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Introduction</a>
                <a href="#authentication" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Authentication</a>
                <a href="#errors" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Errors</a>

                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-6 mb-2 px-3">Checkout</div>
                <a href="#create-checkout" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Create Session</a>
                <a href="#get-checkout" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Retrieve Session</a>
                <a href="#webhooks" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Webhooks</a>

                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-6 mb-2 px-3">Transactions</div>
                <a href="#list-transactions" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">List Transactions</a>
                <a href="#get-transaction" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Retrieve Transaction</a>
                <a href="#refund" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Refund</a>

                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-6 mb-2 px-3">Payouts</div>
                <a href="#create-payout" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Create Payout</a>
                <a href="#get-payout" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Retrieve Payout</a>

                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-6 mb-2 px-3">Resources</div>
                <a href="#sdks" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">SDKs & Libraries</a>
                <a href="#testing" class="sidebar-link block px-3 py-2 rounded-lg text-sm font-medium border-l-2 border-transparent hover:bg-gray-50">Testing</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-64 p-8 max-w-4xl">
            <!-- Introduction -->
            <section id="introduction" class="mb-16">
                <h1 class="text-3xl font-bold text-dark-900 mb-4">SalamPay API Documentation</h1>
                <p class="text-lg text-gray-600 mb-6">
                    Accept payments via Wave, Orange Money, Free Money and more with a single API integration.
                </p>
                <div class="bg-dark-800 rounded-xl p-4 mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-400 text-sm">Base URL</span>
                    </div>
                    <code class="text-primary-400 font-mono">https://api.salamfay.com/v1</code>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-xl p-6 border border-gray-200">
                        <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h3 class="font-semibold text-dark-900 mb-2">Test Mode</h3>
                        <p class="text-gray-600 text-sm">Use test API keys to simulate transactions without real money.</p>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-200">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <h3 class="font-semibold text-dark-900 mb-2">Live Mode</h3>
                        <p class="text-gray-600 text-sm">Switch to live keys when you're ready to accept real payments.</p>
                    </div>
                </div>
            </section>

            <!-- Authentication -->
            <section id="authentication" class="mb-16">
                <h2 class="text-2xl font-bold text-dark-900 mb-4">Authentication</h2>
                <p class="text-gray-600 mb-6">
                    Authenticate your API requests using Bearer tokens. Include your API key in the <code class="bg-gray-100 px-2 py-1 rounded text-sm font-mono">Authorization</code> header.
                </p>
                <div class="bg-dark-800 rounded-xl overflow-hidden mb-6">
                    <div class="flex items-center justify-between px-4 py-2 bg-dark-900 border-b border-dark-700">
                        <span class="text-gray-400 text-sm font-mono">Request Header</span>
                    </div>
                    <pre class="p-4 overflow-x-auto"><code class="text-gray-300 font-mono">Authorization: Bearer spk_live_your_api_key_here</code></pre>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <div class="flex">
                        <svg class="w-5 h-5 text-amber-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div>
                            <h4 class="font-semibold text-amber-800">Keep your API keys secure</h4>
                            <p class="text-amber-700 text-sm">Never expose your secret API keys in client-side code or public repositories.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Create Checkout -->
            <section id="create-checkout" class="mb-16">
                <div class="flex items-center space-x-3 mb-4">
                    <span class="method-post text-white text-xs font-bold px-2 py-1 rounded">POST</span>
                    <h2 class="text-2xl font-bold text-dark-900">Create Checkout Session</h2>
                </div>
                <p class="text-gray-600 mb-6">
                    Create a checkout session to redirect your customer to the SalamPay payment page.
                </p>

                <div class="bg-dark-800 rounded-xl overflow-hidden mb-6">
                    <div class="flex items-center justify-between px-4 py-2 bg-dark-900 border-b border-dark-700">
                        <span class="text-gray-400 text-sm font-mono">POST /v1/checkout/sessions</span>
                    </div>
                    <pre class="p-4 overflow-x-auto"><code class="text-gray-300 font-mono"><span class="text-blue-400">curl</span> https://api.salamfay.com/v1/checkout/sessions \
  -H <span class="text-green-400">"Authorization: Bearer spk_live_..."</span> \
  -H <span class="text-green-400">"Content-Type: application/json"</span> \
  -d <span class="text-green-400">'{
    "amount": 25000,
    "currency": "XOF",
    "provider": "wave",
    "customer": {
      "phone": "+221771234567",
      "name": "Amadou Diallo"
    },
    "metadata": {
      "order_id": "ORD-12345"
    },
    "success_url": "https://example.com/success",
    "cancel_url": "https://example.com/cancel"
  }'</span></code></pre>
                </div>

                <h3 class="font-semibold text-dark-900 mb-3">Request Body</h3>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold text-gray-700">Parameter</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-700">Type</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-700">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">amount</td>
                                <td class="px-4 py-3 text-gray-600">integer</td>
                                <td class="px-4 py-3 text-gray-600"><span class="text-red-500">Required</span>. Amount in smallest currency unit (FCFA)</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">currency</td>
                                <td class="px-4 py-3 text-gray-600">string</td>
                                <td class="px-4 py-3 text-gray-600"><span class="text-red-500">Required</span>. Currency code (XOF)</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">provider</td>
                                <td class="px-4 py-3 text-gray-600">string</td>
                                <td class="px-4 py-3 text-gray-600">Payment provider: wave, orange_money, free_money</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">customer.phone</td>
                                <td class="px-4 py-3 text-gray-600">string</td>
                                <td class="px-4 py-3 text-gray-600"><span class="text-red-500">Required</span>. Customer phone number (E.164 format)</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">success_url</td>
                                <td class="px-4 py-3 text-gray-600">string</td>
                                <td class="px-4 py-3 text-gray-600"><span class="text-red-500">Required</span>. URL to redirect after successful payment</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">cancel_url</td>
                                <td class="px-4 py-3 text-gray-600">string</td>
                                <td class="px-4 py-3 text-gray-600"><span class="text-red-500">Required</span>. URL to redirect if payment is cancelled</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">metadata</td>
                                <td class="px-4 py-3 text-gray-600">object</td>
                                <td class="px-4 py-3 text-gray-600">Custom key-value pairs for your reference</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3 class="font-semibold text-dark-900 mb-3">Response</h3>
                <div class="bg-dark-800 rounded-xl overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2 bg-dark-900 border-b border-dark-700">
                        <span class="text-gray-400 text-sm font-mono">200 OK</span>
                    </div>
                    <pre class="p-4 overflow-x-auto"><code class="text-gray-300 font-mono">{
  <span class="text-blue-400">"id"</span>: <span class="text-green-400">"cs_abc123xyz"</span>,
  <span class="text-blue-400">"object"</span>: <span class="text-green-400">"checkout.session"</span>,
  <span class="text-blue-400">"amount"</span>: <span class="text-orange-400">25000</span>,
  <span class="text-blue-400">"currency"</span>: <span class="text-green-400">"XOF"</span>,
  <span class="text-blue-400">"status"</span>: <span class="text-green-400">"pending"</span>,
  <span class="text-blue-400">"checkout_url"</span>: <span class="text-green-400">"https://pay.salamfay.com/cs_abc123xyz"</span>,
  <span class="text-blue-400">"expires_at"</span>: <span class="text-green-400">"2024-03-20T12:00:00Z"</span>
}</code></pre>
                </div>
            </section>

            <!-- Webhooks -->
            <section id="webhooks" class="mb-16">
                <h2 class="text-2xl font-bold text-dark-900 mb-4">Webhooks</h2>
                <p class="text-gray-600 mb-6">
                    SalamPay sends webhook events to notify your application when payment status changes.
                </p>

                <h3 class="font-semibold text-dark-900 mb-3">Event Types</h3>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold text-gray-700">Event</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-700">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">checkout.session.completed</td>
                                <td class="px-4 py-3 text-gray-600">Payment was successful</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">checkout.session.expired</td>
                                <td class="px-4 py-3 text-gray-600">Session expired before payment</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">payout.completed</td>
                                <td class="px-4 py-3 text-gray-600">Payout was sent successfully</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-primary-600">payout.failed</td>
                                <td class="px-4 py-3 text-gray-600">Payout failed</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3 class="font-semibold text-dark-900 mb-3">Webhook Payload</h3>
                <div class="bg-dark-800 rounded-xl overflow-hidden">
                    <pre class="p-4 overflow-x-auto"><code class="text-gray-300 font-mono">{
  <span class="text-blue-400">"id"</span>: <span class="text-green-400">"evt_123abc"</span>,
  <span class="text-blue-400">"type"</span>: <span class="text-green-400">"checkout.session.completed"</span>,
  <span class="text-blue-400">"created"</span>: <span class="text-orange-400">1710921600</span>,
  <span class="text-blue-400">"data"</span>: {
    <span class="text-blue-400">"object"</span>: {
      <span class="text-blue-400">"id"</span>: <span class="text-green-400">"cs_abc123xyz"</span>,
      <span class="text-blue-400">"amount"</span>: <span class="text-orange-400">25000</span>,
      <span class="text-blue-400">"status"</span>: <span class="text-green-400">"completed"</span>,
      <span class="text-blue-400">"metadata"</span>: {
        <span class="text-blue-400">"order_id"</span>: <span class="text-green-400">"ORD-12345"</span>
      }
    }
  }
}</code></pre>
                </div>
            </section>

            <!-- Errors -->
            <section id="errors" class="mb-16">
                <h2 class="text-2xl font-bold text-dark-900 mb-4">Errors</h2>
                <p class="text-gray-600 mb-6">
                    SalamPay uses standard HTTP response codes to indicate success or failure.
                </p>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold text-gray-700">Code</th>
                                <th class="text-left px-4 py-3 font-semibold text-gray-700">Meaning</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr><td class="px-4 py-3 font-mono text-green-600">200</td><td class="px-4 py-3 text-gray-600">Success</td></tr>
                            <tr><td class="px-4 py-3 font-mono text-yellow-600">400</td><td class="px-4 py-3 text-gray-600">Bad Request - Invalid parameters</td></tr>
                            <tr><td class="px-4 py-3 font-mono text-red-600">401</td><td class="px-4 py-3 text-gray-600">Unauthorized - Invalid API key</td></tr>
                            <tr><td class="px-4 py-3 font-mono text-red-600">403</td><td class="px-4 py-3 text-gray-600">Forbidden - Access denied</td></tr>
                            <tr><td class="px-4 py-3 font-mono text-red-600">404</td><td class="px-4 py-3 text-gray-600">Not Found - Resource doesn't exist</td></tr>
                            <tr><td class="px-4 py-3 font-mono text-red-600">429</td><td class="px-4 py-3 text-gray-600">Too Many Requests - Rate limit exceeded</td></tr>
                            <tr><td class="px-4 py-3 font-mono text-red-600">500</td><td class="px-4 py-3 text-gray-600">Server Error - Something went wrong</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SDKs -->
            <section id="sdks" class="mb-16">
                <h2 class="text-2xl font-bold text-dark-900 mb-4">SDKs & Libraries</h2>
                <p class="text-gray-600 mb-6">Use our official SDKs for faster integration.</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-xl p-6 border border-gray-200 hover:border-primary-300 transition">
                        <div class="flex items-center space-x-3 mb-3">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center"><span class="text-indigo-600 font-bold">PHP</span></div>
                            <div><h4 class="font-semibold text-dark-900">salampay-php</h4><p class="text-gray-500 text-sm">PHP SDK</p></div>
                        </div>
                        <code class="text-sm font-mono text-gray-600 bg-gray-100 px-2 py-1 rounded">composer require salampay/salampay-php</code>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 hover:border-primary-300 transition">
                        <div class="flex items-center space-x-3 mb-3">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center"><span class="text-yellow-600 font-bold">JS</span></div>
                            <div><h4 class="font-semibold text-dark-900">salampay-js</h4><p class="text-gray-500 text-sm">JavaScript/Node.js SDK</p></div>
                        </div>
                        <code class="text-sm font-mono text-gray-600 bg-gray-100 px-2 py-1 rounded">npm install @salampay/salampay-js</code>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 hover:border-primary-300 transition">
                        <div class="flex items-center space-x-3 mb-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center"><span class="text-green-600 font-bold">Py</span></div>
                            <div><h4 class="font-semibold text-dark-900">salampay-python</h4><p class="text-gray-500 text-sm">Python SDK</p></div>
                        </div>
                        <code class="text-sm font-mono text-gray-600 bg-gray-100 px-2 py-1 rounded">pip install salampay</code>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 hover:border-primary-300 transition">
                        <div class="flex items-center space-x-3 mb-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center"><span class="text-blue-600 font-bold">Dart</span></div>
                            <div><h4 class="font-semibold text-dark-900">salampay-flutter</h4><p class="text-gray-500 text-sm">Flutter/Dart SDK</p></div>
                        </div>
                        <code class="text-sm font-mono text-gray-600 bg-gray-100 px-2 py-1 rounded">flutter pub add salampay</code>
                    </div>
                </div>
            </section>

            <!-- Testing -->
            <section id="testing" class="mb-16">
                <h2 class="text-2xl font-bold text-dark-900 mb-4">Testing</h2>
                <p class="text-gray-600 mb-6">Use test API keys to simulate payments without real money.</p>
                <div class="bg-primary-50 border border-primary-200 rounded-xl p-6 mb-6">
                    <h4 class="font-semibold text-primary-800 mb-3">Test Phone Numbers</h4>
                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                        <div><code class="font-mono text-primary-600">+221700000001</code> - Always succeeds</div>
                        <div><code class="font-mono text-primary-600">+221700000002</code> - Always fails</div>
                        <div><code class="font-mono text-primary-600">+221700000003</code> - Insufficient funds</div>
                        <div><code class="font-mono text-primary-600">+221700000004</code> - Timeout</div>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="border-t border-gray-200 pt-8 mt-16">
                <div class="flex items-center justify-between">
                    <p class="text-gray-500 text-sm">&copy; {{ date('Y') }} SalamPay. All rights reserved.</p>
                    <div class="flex items-center space-x-4">
                        <a href="https://github.com/salampay" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg></a>
                    </div>
                </div>
            </footer>
        </main>
    </div>

    <script>
        // Highlight active section on scroll
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.sidebar-link');

        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (scrollY >= sectionTop - 100) {
                    current = section.getAttribute('id');
                }
            });
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
