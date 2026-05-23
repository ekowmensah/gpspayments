<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Font Awesome --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(34, 197, 94, 0.35), transparent 35%),
                radial-gradient(circle at bottom right, rgba(59, 130, 246, 0.25), transparent 35%),
                linear-gradient(135deg, #020617, #0f172a 45%, #14532d);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #111827;
        }

        .auth-container {
            width: 100%;
            max-width: 980px;
            min-height: 560px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.35);
        }

        .auth-left {
            position: relative;
            padding: 55px;
            color: #ffffff;
            background:
                linear-gradient(rgba(2, 6, 23, 0.75), rgba(20, 83, 45, 0.85)),
                url('https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&w=1200&q=80');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
            padding: 10px 16px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(10px);
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: #22c55e;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #052e16;
            font-size: 18px;
        }

        .auth-left h1 {
            font-size: 42px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 18px;
        }

        .auth-left p {
            max-width: 420px;
            color: rgba(255, 255, 255, 0.86);
            font-size: 16px;
            line-height: 1.7;
        }

        .auth-points {
            display: grid;
            gap: 12px;
            margin-top: 28px;
        }

        .auth-point {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.92);
            font-size: 14px;
        }

        .auth-point i {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(34, 197, 94, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bbf7d0;
        }

        .auth-right {
            padding: 55px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        .login-title {
            margin-bottom: 30px;
        }

        .login-title h2 {
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .login-title p {
            color: #64748b;
            margin: 0;
        }

        .form-label {
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            z-index: 5;
        }

        .form-control-custom {
            height: 54px;
            border-radius: 14px;
            border: 1px solid #dbe4ef;
            padding-left: 48px;
            padding-right: 48px;
            font-size: 15px;
            transition: 0.25s;
            background: #f8fafc;
        }

        .form-control-custom:focus {
            background: #ffffff;
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.14);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #64748b;
            z-index: 6;
        }

        .btn-login {
            height: 54px;
            border-radius: 14px;
            border: none;
            background: linear-gradient(135deg, #16a34a, #14532d);
            color: #ffffff;
            font-weight: 800;
            letter-spacing: 0.3px;
            box-shadow: 0 14px 30px rgba(22, 163, 74, 0.28);
            transition: 0.25s;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 36px rgba(22, 163, 74, 0.36);
            color: #ffffff;
        }

        .error-text {
            color: #dc2626;
            font-size: 13px;
            margin-top: 7px;
        }

        .form-check-input:checked {
            background-color: #16a34a;
            border-color: #16a34a;
        }

        .forgot-link {
            color: #15803d;
            font-weight: 700;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .auth-footer {
            margin-top: 28px;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
        }

        @media (max-width: 850px) {
            .auth-container {
                grid-template-columns: 1fr;
                max-width: 460px;
            }

            .auth-left {
                display: none;
            }

            .auth-right {
                padding: 38px 28px;
            }
        }
    </style>
</head>
<body>

<div class="auth-container">

    <div class="auth-left">
        <div>
            <div class="brand-badge">
                <div class="brand-icon">
                    <i class="fa fa-shield-halved"></i>
                </div>
                {{ config('app.name') }}
            </div>
        </div>

        <div>
            <h1>Welcome back to your dashboard.</h1>
            <p>
                Sign in securely to manage your system, monitor records, view reports,
                and continue from where you stopped.
            </p>

            <div class="auth-points">
                <div class="auth-point">
                    <i class="fa fa-check"></i>
                    Secure user authentication
                </div>
                <div class="auth-point">
                    <i class="fa fa-chart-line"></i>
                    Smart dashboard access
                </div>
                <div class="auth-point">
                    <i class="fa fa-lock"></i>
                    Protected admin area
                </div>
            </div>
        </div>

        <div style="font-size: 13px; color: rgba(255,255,255,0.72);">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>

    <div class="auth-right">

        <div class="login-title">
            <h2>Sign In</h2>
            <p>Use your username or email address to continue.</p>
        </div>

        @if (session('status'))
            <div class="alert alert-success rounded-4">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('login'))
            <div class="alert alert-danger rounded-4">
                {{ $errors->first('login') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <div class="mb-4">
                <label for="login" class="form-label">Username or Email</label>

                <div class="input-group-custom">
                    <i class="fa fa-user input-icon"></i>

                    <input
                        id="login"
                        type="text"
                        name="login"
                        value="{{ old('login') }}"
                        class="form-control form-control-custom @error('login') is-invalid @enderror"
                        placeholder="Enter username or email"
                        required
                        autofocus
                    >
                </div>

                @error('login')
                    <div class="error-text">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>

                <div class="input-group-custom">
                    <i class="fa fa-lock input-icon"></i>

                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="form-control form-control-custom @error('password') is-invalid @enderror"
                        placeholder="Enter password"
                        required
                    >

                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fa fa-eye" id="passwordIcon"></i>
                    </button>
                </div>

                @error('password')
                    <div class="error-text">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="remember"
                        id="remember"
                        {{ old('remember') ? 'checked' : '' }}
                    >

                    <label class="form-check-label" for="remember">
                        Remember me
                    </label>
                </div>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="forgot-link">
                        Forgot password?
                    </a>
                @endif
            </div>

            <button type="submit" class="btn btn-login w-100">
                <i class="fa fa-right-to-bracket me-2"></i>
                Login
            </button>
        </form>

        <div class="auth-footer">
            Powered by {{ config('app.name') }}
        </div>

    </div>

</div>

<script>
    function togglePassword() {
        const password = document.getElementById('password');
        const icon = document.getElementById('passwordIcon');

        if (password.type === 'password') {
            password.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            password.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>
