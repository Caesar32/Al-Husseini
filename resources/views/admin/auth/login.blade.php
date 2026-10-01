@extends('admin.layouts.master-without-nav')

@section('title', 'تسجيل الدخول | مركز الحسيني لخدمات وصيانة السيارات والبطاريات')

@section('css')
<style>
    .auth-one-bg {
        background-image: url('{{ asset('assets/images/auto-service-login-bg.jpg') }}') !important;
        background-position: center center !important;
        background-size: cover !important;
        background-repeat: no-repeat !important;
    }
    .auth-one-bg .bg-overlay {
        background: linear-gradient(135deg, rgba(8, 20, 48, 0.88) 0%, rgba(13, 27, 62, 0.94) 100%) !important;
        opacity: 1 !important;
    }
    .auth-card-master {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
    }
    .auto-showcase-panel {
        background: linear-gradient(180deg, rgba(10, 24, 55, 0.45) 0%, rgba(10, 24, 55, 0.92) 100%), 
                    url('{{ asset('assets/images/auto-service-login-bg.jpg') }}') center center / cover no-repeat;
        position: relative;
        min-height: 480px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 35px;
    }
    .badge-auto-feature {
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #ffffff;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }
</style>
@endsection

@section('content')
<div class="auth-page-wrapper pt-4">
    <!-- auth page bg -->
    <div class="auth-one-bg-position auth-one-bg" id="auth-particles">
        <div class="bg-overlay"></div>

        <div class="shape">
            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1440 120">
                <path d="M 0,36 C 144,53.6 432,123.2 720,124 C 1008,124.8 1296,56.8 1440,40L1440 140L0 140z"></path>
            </svg>
        </div>
    </div>

    <!-- auth page content -->
    <div class="auth-page-content">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-center mt-sm-3 mb-4 text-white">
                        <div>
                            <a href="{{ route('admin.dashboard') }}" class="d-inline-flex flex-column align-items-center auth-logo text-decoration-none">
                                <span class="fs-26 fw-bold text-white">
                                    <i class="ri-car-fill text-warning me-1"></i> مركز الحسيني <span class="text-warning fs-20 fw-normal">| Al-Husseini Auto & Batteries</span>
                                </span>
                            </a>
                        </div>
                        <p class="mt-1 fs-14 fw-medium text-white-50">المنظومة السحابية المتكاملة لمبيعات البطاريات وصيانة السيارات وإدارة الورش</p>
                    </div>
                </div>
            </div>
            <!-- end row -->

            <div class="row justify-content-center">
                <div class="col-xl-10 col-lg-11">
                    <div class="card auth-card-master bg-white overflow-hidden shadow-lg border-0 mb-4">
                        <div class="row g-0">

                            <!-- Visual Showcase Side -->
                            <div class="col-lg-6 d-none d-lg-block">
                                <div class="auto-showcase-panel h-100 text-white">
                                    <div>
                                        <span class="badge bg-warning text-dark px-3 py-2 fw-bold fs-12 rounded-pill shadow-sm mb-3">
                                            <i class="ri-shield-star-line me-1"></i> المركز المعتمد للبطاريات والخدمات
                                        </span>
                                        <h3 class="fw-bold text-white mb-2" style="line-height: 1.4;">
                                            أحدث منافذ صيانة السيارات وتوزيع بطاريات كلورايد وبوش وجيل
                                        </h3>
                                        <p class="text-white-50 fs-13 mb-4">
                                            تشخيص واختبار إلكتروني فوري، إصدار شهادات الضمان الرقمية، ونقاط البيع السريعة.
                                        </p>
                                    </div>

                                    <div class="mt-auto">
                                        <div class="badge-auto-feature d-block">
                                            <i class="ri-battery-charge-line text-warning fs-16"></i>
                                            <strong>فحص واختبار البطاريات:</strong> أجهزة رقمية متطورة وكشف كفاءة الشحن ودينامو السيارة.
                                        </div>
                                        <div class="badge-auto-feature d-block">
                                            <i class="ri-shield-check-line text-success fs-16"></i>
                                            <strong>الضمان والاستبدال الفوري:</strong> ربط سيريال البطارية برقم شاسيه وسيارة العميل.
                                        </div>
                                        <div class="badge-auto-feature d-block mb-0">
                                            <i class="ri-recycle-line text-info fs-16"></i>
                                            <strong>مخزن الكهنة وتجارة الرصاص:</strong> استلام وتدوير البطاريات القديمة بأعلى سعر معتمد.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Login Form Side -->
                            <div class="col-lg-6">
                                <div class="p-lg-5 p-4 d-flex flex-column justify-content-center h-100">
                                    <div class="text-center mb-3">
                                        <h5 class="text-primary fw-bold fs-18">تسجيل الدخول إلى النظام</h5>
                                        <p class="text-muted small">أدخل بيانات الحساب الممنوحة لك من الإدارة للمتابعة.</p>
                                    </div>

                                    @if (session('status'))
                                        <div class="alert alert-success alert-border-left alert-dismissible fade show mb-3" role="alert">
                                            <i class="ri-check-double-line me-2 align-middle fs-16"></i> {{ session('status') }}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    @endif

                                    @if (session('info'))
                                        <div class="alert alert-info alert-border-left alert-dismissible fade show mb-3" role="alert">
                                            <i class="ri-information-line me-2 align-middle fs-16"></i> {{ session('info') }}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    @endif

                                    @php
                                        $effectiveLockout = (int) ($lockoutSeconds ?? 0);
                                        if ($effectiveLockout <= 0 && $errors->has('email')) {
                                            foreach ($errors->get('email') as $err) {
                                                if (preg_match('/(\d+)\s*ثانية/', $err, $matches)) {
                                                    $effectiveLockout = (int) $matches[1];
                                                    break;
                                                }
                                            }
                                        }
                                        if ($effectiveLockout <= 0 && $errors->has('password')) {
                                            foreach ($errors->get('password') as $err) {
                                                if (preg_match('/(\d+)\s*ثانية/', $err, $matches)) {
                                                    $effectiveLockout = (int) $matches[1];
                                                    break;
                                                }
                                            }
                                        }
                                    @endphp

                                    @if ($effectiveLockout > 0)
                                        <div id="login-lockout-alert" class="alert alert-danger alert-border-left alert-dismissible fade show mb-3" role="alert">
                                            <i class="ri-error-warning-line me-2 align-middle fs-16"></i>
                                            <span>تم تجاوز عدد محاولات الدخول المسموح بها. يرجى المحاولة بعد <span id="login-lockout-timer" class="fw-bold">{{ $effectiveLockout }}</span> ثانية.</span>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    @elseif ($errors->any())
                                        <div class="alert alert-danger alert-border-left alert-dismissible fade show mb-3" role="alert">
                                            <i class="ri-error-warning-line me-2 align-middle fs-16"></i>
                                            <ul class="mb-0 ps-3 small">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    @endif

                                    {{-- Account switcher: accounts and (non-production) password come from config
                                         auth.login_switcher via AuthController; nothing is hard-coded here. --}}
                                    @if (!empty($loginSwitcher['accounts']))
                                    <div class="mb-3 p-2 bg-light rounded border" id="account-switcher"
                                         @if (($loginSwitcher['password'] ?? null) !== null) data-password="{{ $loginSwitcher['password'] }}" @endif>
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="fs-11 fw-bold text-muted"><i class="ri-user-shared-line me-1 text-primary"></i> تبديل الحساب:</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach ($loginSwitcher['accounts'] as $account)
                                            <button type="button" class="btn btn-xs btn-outline-{{ $account['style'] }} py-0 px-2 fs-11 account-switch"
                                                    data-login="{{ $account['login'] }}" onclick="fillLogin(this)" @if($effectiveLockout > 0) disabled @endif>
                                                {{ $account['label'] }}
                                            </button>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    <form action="{{ route('admin.login.submit') }}" method="POST" id="login-form">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="loginInput" class="form-label fw-semibold fs-13">اسم المستخدم أو البريد أو الهاتف</label>
                                            <div class="position-relative">
                                                <input type="text" name="login" class="form-control @error('email') is-invalid @enderror" id="loginInput" placeholder="أدخل البريد أو الهاتف أو الاسم" value="{{ old('login', old('email')) }}" @if($effectiveLockout > 0) disabled @else autofocus @endif required>
                                                <span class="position-absolute end-0 top-50 translate-middle-y me-3 text-muted"><i class="ri-user-line"></i></span>
                                            </div>
                                            @error('email')
                                                <div class="invalid-feedback d-block" id="login-field-error">
                                                    @if($effectiveLockout > 0)
                                                        تم تجاوز عدد محاولات الدخول المسموح بها. يرجى المحاولة بعد <span id="login-input-timer" class="fw-bold">{{ $effectiveLockout }}</span> ثانية.
                                                    @else
                                                        {{ $message }}
                                                    @endif
                                                </div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold fs-13" for="password-input">كلمة المرور</label>
                                            <div class="position-relative auth-pass-inputgroup mb-3">
                                                <input type="password" name="password" class="form-control pe-5 password-input @error('password') is-invalid @enderror" placeholder="أدخل كلمة المرور" id="password-input" @if($effectiveLockout > 0) disabled @endif required>
                                                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none" type="button" id="password-addon"><i class="ri-eye-fill align-middle"></i></button>
                                                @error('password')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="remember" value="1" id="auth-remember-check" checked @if($effectiveLockout > 0) disabled @endif>
                                            <label class="form-check-label fs-13 text-muted" for="auth-remember-check">تذكرني على هذا الجهاز</label>
                                        </div>

                                        <div>
                                            <button class="btn btn-primary w-100 fw-bold py-2 fs-14 shadow-sm @if($effectiveLockout > 0) disabled @endif" type="submit" id="btn-login" @if($effectiveLockout > 0) disabled @endif>
                                                <i class="ri-login-box-line me-1 align-middle"></i> تسجيل الدخول
                                            </button>
                                        </div>
                                    </form>

                                    <div class="mt-4 pt-2 border-top text-center">
                                        <p class="text-muted small mb-0">
                                            <i class="ri-shield-keyhole-line text-warning me-1"></i> حسابات الموظفين والمتخصصين يتم تعيينها حصراً من قِبل إدارة النظام.
                                        </p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <!-- end row -->
        </div>
        <!-- end container -->
    </div>
    <!-- end auth page content -->

    <!-- footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-center">
                        <p class="mb-0 text-white-50 small">&copy; {{ date('Y') }} مركز ومجموعة الحسيني لخدمات وصيانة السيارات. جميع الحقوق محفوظة.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!-- end Footer -->
</div>
@endsection

@section('script')
<script src="{{ asset('assets/libs/particles.js/particles.js') }}"></script>
<script src="{{ asset('assets/js/pages/particles.app.js') }}"></script>
<script src="{{ asset('assets/js/pages/password-addon.init.js') }}"></script>
<script>
function fillLogin(button) {
    const loginInput = document.getElementById('loginInput');
    const passInput = document.getElementById('password-input');
    const switcher = document.getElementById('account-switcher');
    const password = switcher ? switcher.dataset.password : undefined;

    if (loginInput) loginInput.value = button.dataset.login;
    if (passInput) {
        passInput.value = password !== undefined ? password : '';
        if (password === undefined) passInput.focus(); // no preset password: the user types it
    }

    document.querySelectorAll('#account-switcher .account-switch').forEach(function (b) {
        b.classList.toggle('active', b === button);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const loginInput = document.getElementById('loginInput');
    const passwordInput = document.getElementById('password-input');
    const rememberCheck = document.getElementById('auth-remember-check');
    const btnLogin = document.getElementById('btn-login');
    const loginForm = document.getElementById('login-form');
    const lockoutAlert = document.getElementById('login-lockout-alert');
    const timerAlertSpan = document.getElementById('login-lockout-timer');
    const timerInputSpan = document.getElementById('login-input-timer');
    const fieldError = document.getElementById('login-field-error');

    let seconds = parseInt('{{ $effectiveLockout }}', 10) || 0;

    if (seconds <= 0 && timerAlertSpan) {
        seconds = parseInt(timerAlertSpan.textContent.trim(), 10) || 0;
    }

    if (seconds > 0) {
        // Disable form elements during lockout
        if (loginInput) {
            loginInput.disabled = true;
            loginInput.setAttribute('disabled', 'disabled');
            loginInput.classList.add('bg-light');
            loginInput.blur();
        }
        if (passwordInput) {
            passwordInput.disabled = true;
            passwordInput.setAttribute('disabled', 'disabled');
            passwordInput.classList.add('bg-light');
        }
        if (rememberCheck) {
            rememberCheck.disabled = true;
        }
        if (btnLogin) {
            btnLogin.disabled = true;
            btnLogin.setAttribute('disabled', 'disabled');
            btnLogin.classList.add('disabled');
        }

        // Real-time live countdown
        const timerInterval = setInterval(function () {
            seconds--;

            if (timerAlertSpan) {
                timerAlertSpan.textContent = seconds;
            }
            if (timerInputSpan) {
                timerInputSpan.textContent = seconds;
            }

            if (seconds <= 0) {
                clearInterval(timerInterval);

                // Automatically re-enable all fields and buttons
                if (loginInput) {
                    loginInput.disabled = false;
                    loginInput.removeAttribute('disabled');
                    loginInput.classList.remove('bg-light');
                    loginInput.focus();
                }
                if (passwordInput) {
                    passwordInput.disabled = false;
                    passwordInput.removeAttribute('disabled');
                    passwordInput.classList.remove('bg-light');
                }
                if (rememberCheck) {
                    rememberCheck.disabled = false;
                }
                if (btnLogin) {
                    btnLogin.disabled = false;
                    btnLogin.removeAttribute('disabled');
                    btnLogin.classList.remove('disabled');
                }

                // Automatically remove lockout alerts without page refresh
                if (lockoutAlert) {
                    lockoutAlert.style.transition = 'opacity 0.5s ease';
                    lockoutAlert.style.opacity = '0';
                    setTimeout(function () {
                        lockoutAlert.remove();
                    }, 500);
                }
                if (fieldError) {
                    fieldError.style.transition = 'opacity 0.5s ease';
                    fieldError.style.opacity = '0';
                    setTimeout(function () {
                        fieldError.remove();
                    }, 500);
                }
            }
        }, 1000);

        // Prevent submission during lockout
        if (loginForm) {
            loginForm.addEventListener('submit', function (e) {
                if (seconds > 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        }
    }
});
</script>
@endsection
