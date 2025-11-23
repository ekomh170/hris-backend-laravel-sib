<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HRIS Backend API - Human Resource Information System</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/png" href="{{ asset('assets/logo-hris.svg') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-text {
            background: linear-gradient(135deg, #2d3748 0%, #5dade2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(93, 173, 226, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Navigation -->
    <nav class="bg-white shadow-sm sticky top-0 z-50 backdrop-blur-lg bg-white/90">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <img src="{{ asset('assets/logo-hris.svg') }}" alt="HRIS Logo" class="w-10 h-10">
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">HRIS Backend</h1>
                        <p class="text-xs text-gray-500">RESTful API Server</p>
                    </div>
                </div>
                @if (Route::has('login'))
                <div class="flex items-center space-x-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="px-5 py-2 bg-gradient-to-r from-gray-700 to-blue-400 text-white rounded-lg hover:from-gray-800 hover:to-blue-500 transition font-medium">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 text-gray-700 hover:text-blue-500 transition font-medium">
                            Login
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="px-5 py-2 bg-gradient-to-r from-gray-700 to-blue-400 text-white rounded-lg hover:from-gray-800 hover:to-blue-500 transition font-medium">
                                Register
                            </a>
                        @endif
                    @endauth
                </div>
                @endif
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-gradient-to-br from-gray-50 via-blue-50 to-sky-50 py-20">
        <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center">
                <div class="inline-flex items-center space-x-2 bg-white rounded-full px-4 py-2 shadow-md mb-6">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                    <span class="text-sm font-semibold text-gray-700">Backend Server Active</span>
                </div>

                <h1 class="text-5xl md:text-7xl font-extrabold text-gray-900 mb-6 leading-tight">
                    Human Resource<br>
                    <span class="gradient-text">Information System</span>
                </h1>

                <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-10 leading-relaxed">
                    Backend API yang powerful dan scalable untuk mengelola seluruh aspek HR:
                    <span class="font-semibold text-blue-600">Employee Management, Dashboard Analytics, Attendance System, Leave Management, Performance Reviews, Salary Slips, dan Real-time Notifications</span>
                    dengan teknologi Laravel terkini dan role-based access control.
                </p>

                <div class="flex flex-wrap justify-center gap-4 mb-12">
                    <a href="#features" class="group px-8 py-4 bg-gradient-to-r from-gray-700 to-blue-500 text-white rounded-xl hover:from-gray-800 hover:to-blue-600 transition-all transform hover:scale-105 font-semibold shadow-lg flex items-center">
                        Explore Features
                        <svg class="w-5 h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                    <a href="#documentation" class="px-8 py-4 bg-white text-gray-900 rounded-xl border-2 border-gray-300 hover:border-blue-500 transition-all font-semibold shadow-lg flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        API Docs
                    </a>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
                    <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                        <div class="text-4xl font-black bg-gradient-to-r from-gray-700 to-blue-500 bg-clip-text text-transparent mb-2">8</div>
                        <div class="text-sm font-medium text-gray-600">Main Modules</div>
                    </div>
                    <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                        <div class="text-4xl font-black bg-gradient-to-r from-blue-500 to-sky-500 bg-clip-text text-transparent mb-2">44</div>
                        <div class="text-sm font-medium text-gray-600">API Endpoints</div>
                    </div>
                    <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                        <div class="text-4xl font-black bg-gradient-to-r from-sky-500 to-cyan-500 bg-clip-text text-transparent mb-2">JWT</div>
                        <div class="text-sm font-medium text-gray-600">Auth System</div>
                    </div>
                    <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                        <div class="text-4xl font-black bg-gradient-to-r from-gray-600 to-blue-600 bg-clip-text text-transparent mb-2">RBAC</div>
                        <div class="text-sm font-medium text-gray-600">Access Control</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    Complete HRIS Features
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Semua yang Anda butuhkan untuk mengelola HR dalam satu sistem
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature Card 1 -->
                <div class="card-hover bg-gradient-to-br from-purple-50 to-pink-100 rounded-2xl p-8 border border-purple-200">
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-600 to-pink-600 rounded-xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Dashboard Analytics</h3>
                    <p class="text-gray-600 leading-relaxed">3 role-specific dashboards dengan real-time analytics: Employee (personal overview), Admin HR (organization metrics), dan Manager (team performance)</p>
                </div>

                <!-- Feature Card 2 -->
                <div class="card-hover bg-gradient-to-br from-gray-50 to-slate-100 rounded-2xl p-8 border border-gray-200">
                    <div class="w-14 h-14 bg-gradient-to-br from-gray-700 to-gray-800 rounded-xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Employee Management</h3>
                    <p class="text-gray-600 leading-relaxed">Kelola data karyawan lengkap dengan role-based access control untuk Admin HR, Manager, dan Employee</p>
                </div>

                <!-- Feature Card 2 -->
                <div class="card-hover bg-gradient-to-br from-blue-50 to-sky-100 rounded-2xl p-8 border border-blue-200">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Attendance System</h3>
                    <p class="text-gray-600 leading-relaxed">Sistem absensi real-time dengan check-in/out, location tracking, dan laporan kehadiran komprehensif</p>
                </div>

                <!-- Feature Card 3 -->
                <div class="card-hover bg-gradient-to-br from-sky-50 to-cyan-100 rounded-2xl p-8 border border-sky-200">
                    <div class="w-14 h-14 bg-gradient-to-br from-sky-500 to-cyan-600 rounded-xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Leave Management</h3>
                    <p class="text-gray-600 leading-relaxed">Proses pengajuan dan approval cuti yang efisien dengan workflow otomatis dan notifikasi real-time</p>
                </div>

                <!-- Feature Card 4 -->
                <div class="card-hover bg-gradient-to-br from-cyan-50 to-teal-100 rounded-2xl p-8 border border-cyan-200">
                    <div class="w-14 h-14 bg-gradient-to-br from-cyan-600 to-teal-600 rounded-xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Salary Slips</h3>
                    <p class="text-gray-600 leading-relaxed">Admin HR input data gaji dengan komponen: basic salary, allowance, deduction. Sistem hitung total otomatis dan generate slip digital</p>
                </div>

                <!-- Feature Card 5 -->
                <div class="card-hover bg-gradient-to-br from-slate-50 to-gray-100 rounded-2xl p-8 border border-slate-200">
                    <div class="w-14 h-14 bg-gradient-to-br from-slate-600 to-gray-700 rounded-xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Performance Review</h3>
                    <p class="text-gray-600 leading-relaxed">Evaluasi kinerja dengan rating 1-10 stars, feedback terstruktur, dan tracking perkembangan karyawan</p>
                </div>

                <!-- Feature Card 6 -->
                <div class="card-hover bg-gradient-to-br from-blue-50 to-indigo-100 rounded-2xl p-8 border border-blue-200">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center mb-6 shadow-lg">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Notifications</h3>
                    <p class="text-gray-600 leading-relaxed">Sistem notifikasi real-time untuk broadcast announcement, personal messages, dan status updates dengan read/unread tracking</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Tech Stack Section -->
    <section id="documentation" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-br from-gray-700 to-blue-500 rounded-3xl p-12 shadow-2xl text-white">
                <div class="text-center mb-12">
                    <h2 class="text-4xl md:text-5xl font-bold mb-4">
                        Technology Stack
                    </h2>
                    <p class="text-xl text-blue-100">
                        Built with modern and proven technologies
                    </p>
                </div>

                <div class="grid md:grid-cols-2 gap-12">
                    <!-- Backend Stack -->
                    <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 border border-white/20">
                        <h3 class="text-2xl font-bold mb-6 flex items-center">
                            <svg class="w-7 h-7 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                            </svg>
                            Backend Technology
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Framework</span>
                                <span class="bg-white/20 px-4 py-2 rounded-lg font-bold">Laravel 12.0</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">PHP Version</span>
                                <span class="bg-white/20 px-4 py-2 rounded-lg font-bold">8.2.12</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Database</span>
                                <span class="bg-white/20 px-4 py-2 rounded-lg font-bold">MySQL</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Authentication</span>
                                <span class="bg-white/20 px-4 py-2 rounded-lg font-bold">JWT v2.2</span>
                            </div>
                        </div>
                    </div>

                    <!-- API Information -->
                    <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-8 border border-white/20">
                        <h3 class="text-2xl font-bold mb-6 flex items-center">
                            <svg class="w-7 h-7 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            API Information
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Base URL</span>
                                <span class="bg-white/20 px-4 py-2 rounded-lg font-bold">/api</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Auth Type</span>
                                <span class="bg-white/20 px-4 py-2 rounded-lg font-bold">Bearer Token</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Response Format</span>
                                <span class="bg-white/20 px-4 py-2 rounded-lg font-bold">JSON</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Total Endpoints</span>
                                <span class="bg-white/20 px-4 py-2 rounded-lg font-bold">44 Routes</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- API Modules Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    8 Main API Modules
                </h2>
                <p class="text-xl text-gray-600">
                    Complete endpoints dengan role-based access untuk semua kebutuhan HR Management
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="group bg-white rounded-xl p-6 border-2 border-purple-300 hover:border-purple-600 hover:shadow-xl transition-all">
                    <div class="text-4xl mb-3">📊</div>
                    <h4 class="font-bold text-gray-900 mb-2 text-lg">Dashboard Analytics</h4>
                    <p class="text-sm text-gray-600">3 Role-specific Dashboards: Employee, Admin HR, Manager dengan Real-time Analytics</p>
                </div>

                <div class="group bg-white rounded-xl p-6 border-2 border-gray-300 hover:border-gray-600 hover:shadow-xl transition-all">
                    <div class="text-4xl mb-3">🔐</div>
                    <h4 class="font-bold text-gray-900 mb-2 text-lg">Authentication</h4>
                    <p class="text-sm text-gray-600">Login, Logout, Refresh Token, User Profile</p>
                </div>

                <div class="group bg-white rounded-xl p-6 border-2 border-blue-300 hover:border-blue-600 hover:shadow-xl transition-all">
                    <div class="text-4xl mb-3">👥</div>
                    <h4 class="font-bold text-gray-900 mb-2 text-lg">Employee Management</h4>
                    <p class="text-sm text-gray-600">CRUD Karyawan, Manager List, Profile Management, Role-based Access Control</p>
                </div>

                <div class="group bg-white rounded-xl p-6 border-2 border-blue-300 hover:border-blue-600 hover:shadow-xl transition-all">
                    <div class="text-4xl mb-3">⏰</div>
                    <h4 class="font-bold text-gray-900 mb-2 text-lg">Attendance</h4>
                    <p class="text-sm text-gray-600">Check In/Out, History, Location Tracking</p>
                </div>

                <div class="group bg-white rounded-xl p-6 border-2 border-sky-300 hover:border-sky-600 hover:shadow-xl transition-all">
                    <div class="text-4xl mb-3">🏖️</div>
                    <h4 class="font-bold text-gray-900 mb-2 text-lg">Leave Requests</h4>
                    <p class="text-sm text-gray-600">Apply Leave, Approval Workflow, Status</p>
                </div>

                <div class="group bg-white rounded-xl p-6 border-2 border-cyan-300 hover:border-cyan-600 hover:shadow-xl transition-all">
                    <div class="text-4xl mb-3">💰</div>
                    <h4 class="font-bold text-gray-900 mb-2 text-lg">Salary Slips</h4>
                    <p class="text-sm text-gray-600">Manage Salary Data, Components, Slip Generation</p>
                </div>

                <div class="group bg-white rounded-xl p-6 border-2 border-slate-300 hover:border-slate-600 hover:shadow-xl transition-all">
                    <div class="text-4xl mb-3">⭐</div>
                    <h4 class="font-bold text-gray-900 mb-2 text-lg">Performance Reviews</h4>
                    <p class="text-sm text-gray-600">1-10 Star Rating System, Structured Feedback, Progress Tracking</p>
                </div>

                <div class="group bg-white rounded-xl p-6 border-2 border-indigo-300 hover:border-indigo-600 hover:shadow-xl transition-all">
                    <div class="text-4xl mb-3">🔔</div>
                    <h4 class="font-bold text-gray-900 mb-2 text-lg">Notifications</h4>
                    <p class="text-sm text-gray-600">Personal Messages, Broadcast Announcements, Read/Unread Tracking</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="flex justify-center items-center space-x-3 mb-6">
                    <img src="{{ asset('assets/logo-hris.svg') }}" alt="HRIS Logo" class="w-12 h-12">
                    <div class="text-left">
                        <div class="text-xl font-bold">HRIS Backend API</div>
                        <div class="text-sm text-gray-400">Human Resource Information System</div>
                    </div>
                </div>

                <div class="border-t border-gray-800 pt-8 mt-8">
                    <p class="text-gray-400 mb-4">
                        <span class="font-semibold text-white">FWD Batch 3 Team</span>
                    </p>
                    <div class="flex justify-center items-center space-x-4 text-sm text-gray-500">
                        <span>Laravel {{ app()->version() }}</span>
                        <span>•</span>
                        <span>PHP {{ PHP_VERSION }}</span>
                        <span>•</span>
                        <span>© 2025 All Rights Reserved</span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
