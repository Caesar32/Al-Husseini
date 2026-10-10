@extends('admin.layouts.master-without-nav')

@section('title', 'قفل الشاشة | نظام الحسيني Al-Husseini')

@section('content')
<div class="auth-page-wrapper pt-5">
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
                    <div class="text-center mt-sm-5 mb-4 text-white">
                        <div>
                            <a href="{{ route('admin.dashboard') }}" class="d-inline-flex flex-column align-items-center auth-logo text-decoration-none">
                                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="64" class="rounded-circle shadow-lg mb-2" style="border: 2px solid rgba(212, 175, 55, 0.7);">
                                <span class="fs-22 fw-bold text-white">مجموعة الحسيني <span class="text-warning fs-16 fw-normal">| Al-Husseini</span></span>
                            </a>
                        </div>
                        <p class="mt-2 fs-14 fw-medium text-white-50">نظام إدارة فروع وورش بطاريات السيارات</p>
                    </div>
                </div>
            </div>
            <!-- end row -->

            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card mt-4 card-bg-fill border-0 shadow-lg">

                        <div class="card-body p-4 text-center">
                            <div class="mt-2">
                                <h5 class="text-primary fw-bold">الشاشة مقفلة</h5>
                                <p class="text-muted">أدخل كلمة المرور لفتح الشاشة واستئناف العمل</p>
                            </div>

                            <div class="user-thumb text-center my-3">
                                <img src="{{ $user->avatarUrl() }}" class="rounded-circle img-thumbnail avatar-lg shadow" alt="thumbnail">
                                <h5 class="font-size-15 mt-3 fw-bold text-dark">{{ $user->name }}</h5>
                                <p class="text-muted fs-13 mb-0">
                                    <span class="badge bg-primary-subtle text-primary">{{ $user->roles->first()?->name ?? 'super-admin' }}</span>
                                    <span class="text-muted mx-1">•</span>
                                    <span>{{ $user->email }}</span>
                                </p>
                            </div>

                            @if ($lockoutSeconds > 0)
                                <div class="alert alert-warning alert-border-left text-start my-3" role="alert" id="lockout-alert">
                                    <i class="ri-timer-line me-2 align-middle fs-16"></i>
                                    <strong>تم إيقاف محاولات فتح الشاشة مؤقتاً.</strong>
                                    <div class="mt-1">
                                        يرجى الانتظار
                                        <strong id="lockout-countdown">{{ $lockoutSeconds }}</strong>
                                        ثانية قبل المحاولة مرة أخرى.
                                    </div>
                                </div>
                            @elseif ($errors->any())
                                <div class="alert alert-danger alert-border-left alert-dismissible fade show text-start my-3" role="alert">
                                    <i class="ri-error-warning-line me-2 align-middle fs-16"></i>
                                    @foreach ($errors->all() as $error)
                                        <span>{{ $error }}</span>
                                    @endforeach
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <div class="p-2 mt-2">
                                <form action="{{ route('admin.lockscreen.unlock') }}" method="POST">
                                    @csrf
                                    <div class="mb-3 text-start">
                                        <label class="form-label" for="userpassword">كلمة المرور</label>
                                        <div class="position-relative auth-pass-inputgroup mb-3">
                                            <input type="password" name="password" class="form-control pe-5 password-input @error('password') is-invalid @enderror" placeholder="أدخل كلمة المرور لفتح الشاشة" id="userpassword" autofocus required @disabled($lockoutSeconds > 0)>
                                            <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none" type="button" id="password-addon" @disabled($lockoutSeconds > 0)><i class="ri-eye-fill align-middle"></i></button>
                                        </div>
                                    </div>

                                    <div class="mb-2 mt-4">
                                        <button class="btn btn-primary w-100 fw-semibold" type="submit" id="unlock-button" @disabled($lockoutSeconds > 0)>
                                            <i class="ri-lock-unlock-line align-middle me-1"></i> فتح الشاشة
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <!-- end card body -->
                    </div>
                    <!-- end card -->

                    <div class="mt-4 text-center">
                        <p class="mb-0 text-white-50">لست {{ $user->name }} ؟
                            <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-link text-white fw-bold text-decoration-underline p-0 m-0 align-baseline">
                                    تسجيل الدخول بحساب آخر
                                </button>
                            </form>
                        </p>
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
                        <p class="mb-0 text-muted">&copy; {{ date('Y') }} {{ config('app.name', 'Al-Husseini') }}. جميع الحقوق محفوظة.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!-- end Footer -->
</div>
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const passwordInput = document.getElementById('userpassword');
        const passwordAddon = document.getElementById('password-addon');
        const unlockButton = document.getElementById('unlock-button');
        const lockoutAlert = document.getElementById('lockout-alert');
        const countdownElement = document.getElementById('lockout-countdown');

        passwordAddon?.addEventListener('click', function () {
            if (passwordInput.disabled) {
                return;
            }

            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        });

        const lockoutUntil = @json($lockoutUntil);

        if (!lockoutUntil || !countdownElement || !lockoutAlert) {
            return;
        }

        let timerId = null;

        const setLockedState = (locked) => {
            passwordInput.disabled = locked;
            passwordAddon.disabled = locked;
            unlockButton.disabled = locked;
        };

        const finishLockout = () => {
            if (timerId !== null) {
                clearInterval(timerId);
                timerId = null;
            }

            lockoutAlert.remove();
            passwordInput.disabled = false;
            passwordAddon.disabled = false;
            unlockButton.disabled = false;
            passwordInput.value = '';
            passwordInput.focus();
        };

        const updateCountdown = () => {
            const remaining = Math.max(
                0,
                Math.ceil((lockoutUntil * 1000 - Date.now()) / 1000)
            );

            countdownElement.textContent = remaining;

            if (remaining <= 0) {
                finishLockout();
            }
        };

        setLockedState(true);
        updateCountdown();
        timerId = window.setInterval(updateCountdown, 1000);
    });
</script>
@endsection
