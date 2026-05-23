<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Member Portal') - {{ config('app.name', 'GPS Payments') }}</title>

    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte.min.css') }}">

    <style>
        :root {
            --portal-primary: #0f4c81;
            --portal-accent: #1f7a5a;
            --portal-bg: #f3f6fb;
            --portal-ink: #12263a;
            --portal-line: #dfe7f1;
        }
        body {
            background: radial-gradient(circle at top right, #e6eef8 0%, #f5f8fc 42%, #f7f9fd 100%);
            color: var(--portal-ink);
            min-height: 100vh;
        }
        .portal-nav {
            background: linear-gradient(120deg, #0b2f4d 0%, #0f4c81 55%, #1667a7 100%);
            border-bottom: 1px solid rgba(255,255,255,.14);
            box-shadow: 0 12px 28px rgba(10, 32, 54, .18);
        }
        .portal-nav .navbar-brand,
        .portal-nav .nav-link,
        .portal-nav .navbar-text {
            color: #fff !important;
        }
        .portal-shell {
            max-width: 1320px;
            margin: 1.25rem auto 1.75rem;
            padding: 0 .9rem;
        }
        .portal-header {
            background: linear-gradient(140deg, rgba(255,255,255,.96) 0%, rgba(240,246,255,.96) 100%);
            border: 1px solid var(--portal-line);
            border-radius: 1rem;
            box-shadow: 0 12px 30px rgba(18, 38, 58, .08);
            padding: 1rem 1.1rem;
            margin-bottom: 1rem;
        }
        .portal-footer {
            color: #5c6f83;
            font-size: .85rem;
            text-align: center;
            padding: 1rem 0 1.5rem;
        }
        .portal-card {
            border: 1px solid var(--portal-line);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(18, 38, 58, .07);
        }
        .portal-card .card-header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border-bottom: 1px solid var(--portal-line);
        }
    </style>

    @yield('css')
</head>
<body>
    <nav class="navbar navbar-expand-lg portal-nav">
        <div class="container-fluid">
            <a class="navbar-brand font-weight-bold" href="{{ route('member-portal.index') }}">
                <i class="fas fa-id-card mr-2"></i> Member Portal
            </a>

            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#portalNav" aria-controls="portalNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" style="filter:invert(1);"></span>
            </button>

            <div class="collapse navbar-collapse" id="portalNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('member-portal.index') ? 'font-weight-bold' : '' }}" href="{{ route('member-portal.index') }}">
                            <i class="fas fa-home mr-1"></i> Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('member-portal.statement') ? 'font-weight-bold' : '' }}" href="{{ route('member-portal.statement') }}">
                            <i class="fas fa-book-open mr-1"></i> Full Statement
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('member-portal.profile') ? 'font-weight-bold' : '' }}" href="{{ route('member-portal.profile') }}">
                            <i class="fas fa-user-circle mr-1"></i> My Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('member-portal.statement.export') }}">
                            <i class="fas fa-file-csv mr-1"></i> Statement CSV
                        </a>
                    </li>
                </ul>

                <span class="navbar-text mr-3 d-none d-lg-inline">
                    {{ auth()->user()?->fullName() }}
                </span>

                @if(auth()->user()?->hasRole('Administrator', 'Treasurer', 'Secretary', 'Auditor'))
                    <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm mr-2">
                        <i class="fas fa-tachometer-alt mr-1"></i> Admin Dashboard
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="portal-shell">
        <section class="portal-header">
            @yield('content_header')
        </section>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <div class="portal-footer">
        Powered by {{ config('app.name', 'GPS Payments') }}
    </div>

    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/adminlte/dist/js/adminlte.min.js') }}"></script>
    @yield('js')
</body>
</html>
