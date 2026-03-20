<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SalamPay - La passerelle de paiement moderne pour le Senegal. Acceptez Wave, Orange Money et plus encore.">
    <title>SalamPay - Paiements Simplifies pour le Senegal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        },
                        dark: {
                            900: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #064e3b 0%, #0f172a 50%, #1e293b 100%);
        }
        .hero-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2310b981' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body class="font-sans antialiased text-gray-900 bg-white">
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-primary-500 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-dark-900">Salam<span class="text-primary-500">Pay</span></span>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-gray-600 hover:text-primary-600 font-medium transition">Fonctionnalites</a>
                    <a href="#pricing" class="text-gray-600 hover:text-primary-600 font-medium transition">Tarifs</a>
                    <a href="#developers" class="text-gray-600 hover:text-primary-600 font-medium transition">Developpeurs</a>
                    <a href="#contact" class="text-gray-600 hover:text-primary-600 font-medium transition">Contact</a>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <a href="/login" class="text-gray-700 hover:text-primary-600 font-medium transition">Connexion</a>
                    <a href="/register" class="bg-primary-500 hover:bg-primary-600 text-white px-5 py-2.5 rounded-lg font-medium transition shadow-lg shadow-primary-500/25">Commencer</a>
                </div>
                <button class="md:hidden p-2 rounded-lg hover:bg-gray-100" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
            <div id="mobile-menu" class="hidden md:hidden pb-4">
                <div class="flex flex-col space-y-3">
                    <a href="#features" class="text-gray-600 hover:text-primary-600 font-medium py-2">Fonctionnalites</a>
                    <a href="#pricing" class="text-gray-600 hover:text-primary-600 font-medium py-2">Tarifs</a>
                    <a href="#developers" class="text-gray-600 hover:text-primary-600 font-medium py-2">Developpeurs</a>
                    <a href="/login" class="text-gray-700 font-medium py-2">Connexion</a>
                    <a href="/register" class="bg-primary-500 text-white px-5 py-2.5 rounded-lg font-medium text-center">Commencer</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="gradient-bg hero-pattern pt-32 pb-20 lg:pt-40 lg:pb-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <div class="inline-flex items-center bg-white/10 backdrop-blur-sm rounded-full px-4 py-2 mb-6">
                        <span class="w-2 h-2 bg-primary-400 rounded-full mr-2 animate-pulse"></span>
                        <span class="text-primary-200 text-sm font-medium">Nouveau: Integration Orange Money disponible</span>
                    </div>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white leading-tight mb-6">
                        Paiements <span class="text-primary-400">simplifies</span> pour le Senegal
                    </h1>
                    <p class="text-lg md:text-xl text-gray-300 mb-8 max-w-xl">
                        Acceptez Wave, Orange Money, Free Money et plus encore avec une seule API. Des frais competitifs et des virements rapides.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <a href="/register" class="bg-primary-500 hover:bg-primary-400 text-white px-8 py-4 rounded-xl font-semibold text-lg transition shadow-xl shadow-primary-500/30 flex items-center justify-center">
                            Creer un compte gratuit
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                        <a href="#developers" class="bg-white/10 hover:bg-white/20 text-white px-8 py-4 rounded-xl font-semibold text-lg transition backdrop-blur-sm border border-white/20 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                            Documentation API
                        </a>
                    </div>
                    <div class="grid grid-cols-3 gap-8 mt-12 pt-8 border-t border-white/10">
                        <div><div class="text-3xl font-bold text-white">1%</div><div class="text-gray-400 text-sm">Frais</div></div>
                        <div><div class="text-3xl font-bold text-white">24h</div><div class="text-gray-400 text-sm">Virements</div></div>
                        <div><div class="text-3xl font-bold text-white">99.9%</div><div class="text-gray-400 text-sm">Uptime</div></div>
                    </div>
                </div>
                <div class="relative hidden lg:block">
                    <div class="relative animate-float">
                        <div class="bg-white rounded-3xl shadow-2xl p-8 max-w-md mx-auto">
                            <div class="flex items-center justify-between mb-6">
                                <span class="text-gray-500 text-sm">Paiement recu</span>
                                <span class="text-primary-500 text-sm font-medium">Aujourd'hui</span>
                            </div>
                            <div class="text-4xl font-bold text-dark-900 mb-2">25,000 FCFA</div>
                            <div class="text-gray-500 mb-6">de Amadou Diallo</div>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center"><span class="text-white font-bold text-sm">W</span></div>
                                    <span class="font-medium">Wave</span>
                                </div>
                                <span class="text-primary-500 font-semibold">Complete</span>
                            </div>
                        </div>
                        <div class="absolute -top-4 -right-4 bg-orange-500 text-white px-4 py-2 rounded-xl shadow-lg"><span class="font-semibold">+15,000 FCFA</span></div>
                        <div class="absolute -bottom-4 -left-4 bg-primary-500 text-white px-4 py-2 rounded-xl shadow-lg flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span class="font-semibold">Securise</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Payment Methods -->
    <section class="py-12 bg-gray-50 border-y border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 mb-8">Acceptez tous les moyens de paiement populaires au Senegal</p>
            <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16">
                <div class="flex items-center space-x-2 grayscale hover:grayscale-0 transition"><div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center"><span class="text-white font-bold text-xl">W</span></div><span class="font-semibold text-gray-700">Wave</span></div>
                <div class="flex items-center space-x-2 grayscale hover:grayscale-0 transition"><div class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center"><span class="text-white font-bold text-xl">OM</span></div><span class="font-semibold text-gray-700">Orange Money</span></div>
                <div class="flex items-center space-x-2 grayscale hover:grayscale-0 transition"><div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center"><span class="text-white font-bold text-xl">FM</span></div><span class="font-semibold text-gray-700">Free Money</span></div>
                <div class="flex items-center space-x-2 grayscale hover:grayscale-0 transition"><div class="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center"><span class="text-white font-bold text-xl">EM</span></div><span class="font-semibold text-gray-700">E-Money</span></div>
                <div class="flex items-center space-x-2 grayscale hover:grayscale-0 transition"><div class="w-12 h-12 bg-dark-900 rounded-full flex items-center justify-center"><span class="text-white font-bold text-sm">VISA</span></div><span class="font-semibold text-gray-700">Cartes</span></div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 lg:py-28">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-dark-900 mb-4">Tout ce dont vous avez besoin pour accepter des paiements</h2>
                <p class="text-lg text-gray-600">Une plateforme complete concue pour les entreprises senegalaises.</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="w-14 h-14 bg-primary-100 rounded-xl flex items-center justify-center mb-6"><svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                    <h3 class="text-xl font-bold text-dark-900 mb-3">Integration rapide</h3>
                    <p class="text-gray-600">Integrez SalamPay en quelques minutes avec notre API RESTful simple et notre documentation complete.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6"><svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></div>
                    <h3 class="text-xl font-bold text-dark-900 mb-3">Securite maximale</h3>
                    <p class="text-gray-600">Conformite PCI-DSS, chiffrement de bout en bout et detection des fraudes.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="w-14 h-14 bg-orange-100 rounded-xl flex items-center justify-center mb-6"><svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
                    <h3 class="text-xl font-bold text-dark-900 mb-3">Tableau de bord complet</h3>
                    <p class="text-gray-600">Suivez vos transactions, analysez vos ventes et gerez vos remboursements.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mb-6"><svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
                    <h3 class="text-xl font-bold text-dark-900 mb-3">Multi-devises</h3>
                    <p class="text-gray-600">Acceptez les paiements en FCFA et convertissez automatiquement.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center mb-6"><svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                    <h3 class="text-xl font-bold text-dark-900 mb-3">Virements rapides</h3>
                    <p class="text-gray-600">Recevez vos fonds en 24h sur votre compte Wave, Orange Money ou bancaire.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-gray-100 card-hover">
                    <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center mb-6"><svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg></div>
                    <h3 class="text-xl font-bold text-dark-900 mb-3">Support 24/7</h3>
                    <p class="text-gray-600">Notre equipe est disponible en permanence en francais et en wolof.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 lg:py-28 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-dark-900 mb-4">Tarification transparente</h2>
                <p class="text-lg text-gray-600">Pas de frais caches. Payez uniquement pour les transactions effectuees.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <div class="bg-white rounded-2xl p-8 border border-gray-200 card-hover">
                    <h3 class="text-lg font-semibold text-gray-500 mb-2">Standard</h3>
                    <div class="flex items-end mb-6"><span class="text-4xl font-bold text-dark-900">1%</span><span class="text-gray-500 ml-2">/ transaction</span></div>
                    <p class="text-gray-600 mb-6">Ideal pour les petites entreprises et les startups.</p>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center text-gray-600"><svg class="w-5 h-5 text-primary-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Wave, Orange Money, Free Money</li>
                        <li class="flex items-center text-gray-600"><svg class="w-5 h-5 text-primary-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Tableau de bord complet</li>
                        <li class="flex items-center text-gray-600"><svg class="w-5 h-5 text-primary-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Virements en 24-48h</li>
                    </ul>
                    <a href="/register" class="block w-full text-center py-3 rounded-xl font-semibold border-2 border-primary-500 text-primary-600 hover:bg-primary-500 hover:text-white transition">Commencer gratuitement</a>
                </div>
                <div class="bg-dark-900 rounded-2xl p-8 card-hover relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-primary-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg">POPULAIRE</div>
                    <h3 class="text-lg font-semibold text-gray-400 mb-2">Business</h3>
                    <div class="flex items-end mb-6"><span class="text-4xl font-bold text-white">0.8%</span><span class="text-gray-400 ml-2">/ transaction</span></div>
                    <p class="text-gray-400 mb-6">Pour les entreprises en croissance.</p>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center text-gray-300"><svg class="w-5 h-5 text-primary-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Tout du plan Standard</li>
                        <li class="flex items-center text-gray-300"><svg class="w-5 h-5 text-primary-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Virements quotidiens</li>
                        <li class="flex items-center text-gray-300"><svg class="w-5 h-5 text-primary-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Support prioritaire</li>
                    </ul>
                    <a href="/register" class="block w-full text-center py-3 rounded-xl font-semibold bg-primary-500 text-white hover:bg-primary-400 transition">Commencer maintenant</a>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-gray-200 card-hover">
                    <h3 class="text-lg font-semibold text-gray-500 mb-2">Enterprise</h3>
                    <div class="flex items-end mb-6"><span class="text-4xl font-bold text-dark-900">Sur mesure</span></div>
                    <p class="text-gray-600 mb-6">Solutions personnalisees pour les grandes entreprises.</p>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-center text-gray-600"><svg class="w-5 h-5 text-primary-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Tarifs negocies</li>
                        <li class="flex items-center text-gray-600"><svg class="w-5 h-5 text-primary-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Gestionnaire de compte</li>
                        <li class="flex items-center text-gray-600"><svg class="w-5 h-5 text-primary-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>SLA garanti</li>
                    </ul>
                    <a href="#contact" class="block w-full text-center py-3 rounded-xl font-semibold border-2 border-gray-300 text-gray-700 hover:border-primary-500 hover:text-primary-600 transition">Nous contacter</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Developer Section -->
    <section id="developers" class="py-20 lg:py-28 bg-dark-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">Concu pour les developpeurs</h2>
                    <p class="text-lg text-gray-400 mb-8">Une API RESTful intuitive avec documentation complete, SDKs et environnement sandbox.</p>
                    <div class="space-y-4 mb-8">
                        <div class="flex items-center text-gray-300"><svg class="w-5 h-5 text-primary-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Documentation complete avec exemples</div>
                        <div class="flex items-center text-gray-300"><svg class="w-5 h-5 text-primary-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>SDKs pour PHP, Python, JavaScript, Flutter</div>
                        <div class="flex items-center text-gray-300"><svg class="w-5 h-5 text-primary-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Webhooks temps reel</div>
                    </div>
                    <a href="/api/docs" class="inline-flex items-center bg-primary-500 hover:bg-primary-400 text-white px-6 py-3 rounded-xl font-semibold transition">Voir la documentation<svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></a>
                </div>
                <div class="bg-dark-800 rounded-2xl p-6 overflow-hidden">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        <span class="text-gray-500 text-sm ml-4">checkout.php</span>
                    </div>
                    <pre class="text-sm overflow-x-auto"><code class="text-gray-300"><span class="text-purple-400">$response</span> = <span class="text-blue-400">SalamPay</span>::<span class="text-yellow-400">checkout</span>([
    <span class="text-green-400">'amount'</span>    => <span class="text-orange-400">25000</span>,
    <span class="text-green-400">'currency'</span>  => <span class="text-green-400">'XOF'</span>,
    <span class="text-green-400">'provider'</span>  => <span class="text-green-400">'wave'</span>,
    <span class="text-green-400">'customer'</span>  => [
        <span class="text-green-400">'phone'</span> => <span class="text-green-400">'+221771234567'</span>,
    ],
]);</code></pre>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 lg:py-28 bg-primary-600">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-6">Pret a developper votre activite?</h2>
            <p class="text-lg text-primary-100 mb-8 max-w-2xl mx-auto">Rejoignez des centaines d'entreprises senegalaises qui font confiance a SalamPay.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/register" class="bg-white text-primary-600 px-8 py-4 rounded-xl font-semibold text-lg hover:bg-gray-100 transition shadow-xl">Creer un compte gratuit</a>
                <a href="#contact" class="bg-primary-700 text-white px-8 py-4 rounded-xl font-semibold text-lg hover:bg-primary-800 transition border border-primary-500">Parler a un expert</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="bg-dark-900 pt-16 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-10 h-10 bg-primary-500 rounded-xl flex items-center justify-center"><svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                        <span class="text-xl font-bold text-white">Salam<span class="text-primary-400">Pay</span></span>
                    </div>
                    <p class="text-gray-400 mb-4">La passerelle de paiement moderne pour le Senegal.</p>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Produits</h4>
                    <ul class="space-y-3"><li><a href="#" class="text-gray-400 hover:text-primary-400 transition">Checkout</a></li><li><a href="#" class="text-gray-400 hover:text-primary-400 transition">Liens de paiement</a></li><li><a href="#" class="text-gray-400 hover:text-primary-400 transition">Facturation</a></li></ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Developpeurs</h4>
                    <ul class="space-y-3"><li><a href="/api/docs" class="text-gray-400 hover:text-primary-400 transition">Documentation API</a></li><li><a href="#" class="text-gray-400 hover:text-primary-400 transition">SDKs</a></li><li><a href="#" class="text-gray-400 hover:text-primary-400 transition">Statut</a></li></ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Contact</h4>
                    <ul class="space-y-3">
                        <li class="flex items-center text-gray-400"><svg class="w-5 h-5 mr-2 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>contact@salamfay.com</li>
                        <li class="flex items-center text-gray-400"><svg class="w-5 h-5 mr-2 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>+221 33 800 00 00</li>
                        <li class="flex items-start text-gray-400"><svg class="w-5 h-5 mr-2 mt-0.5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Dakar, Senegal</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-dark-700 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-500 text-sm mb-4 md:mb-0">&copy; {{ date('Y') }} SalamPay. Tous droits reserves.</p>
                    <div class="flex space-x-6"><a href="#" class="text-gray-500 hover:text-primary-400 text-sm transition">Mentions legales</a><a href="#" class="text-gray-500 hover:text-primary-400 text-sm transition">Confidentialite</a><a href="#" class="text-gray-500 hover:text-primary-400 text-sm transition">CGU</a></div>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
