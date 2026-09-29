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
                                <img src="{{ $user->avatar ? asset('uploads/avatars/' . $user->avatar) : asset('assets/images/users/avatar-1.jpg') }}" class="rounded-circle img-thumbnail avatar-lg shadow" alt="thumbnail">
                                <h5 class="font-size-15 mt-3 fw-bold text-dark">{{ $user->name }}</h5>
                                <p class="text-muted fs-13 mb-0">
                                    <span class="badge bg-primary-subtle text-primary">{{ $user->roles->first()?->name ?? 'super-admin' }}</span>
                                    <span class="text-muted mx-1">•</span>
                                    <span>{{ $user->email }}</span>
                                </p>
                            </div>

                            @php
                                $effectiveLockout = (int) ($lockoutSeconds ?? 0);
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
                                <div id="lockout-alert" class="alert alert-danger alert-border-left alert-dismissible fade show text-start my-3" role="alert">
                                    <i class="ri-error-warning-line me-2 align-middle fs-16"></i>
                                    <span>تم تجاوز عدد محاولات فتح الشاشة المسموح بها. يرجى الانتظار <span id="lockout-timer" class="fw-bold">{{ $effectiveLockout }}</span> ثانية.</span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                                <form action="{{ route('admin.lockscreen.unlock') }}" method="POST" id="lockscreen-form">
                                    @csrf
                                    <div class="mb-3 text-start">
                                        <label class="form-label" for="userpassword">كلمة المرور</label>
                                        <div class="position-relative auth-pass-inputgroup mb-3">
                                            <input type="password" name="password" class="form-control pe-5 password-input @error('password') is-invalid @enderror" placeholder="أدخل كلمة المرور لفتح الشاشة" id="userpassword" @if($effectiveLockout > 0) disabled @else autofocus @endif required>
                                            <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none" type="button" id="password-addon"><i class="ri-eye-fill align-middle"></i></button>
                                        </div>
                                    </div>

                                    <div class="mb-2 mt-4">
                                        <button class="btn btn-primary w-100 fw-semibold @if($effectiveLockout > 0) disabled @endif" type="submit" id="btn-unlock" @if($effectiveLockout > 0) disabled @endif>
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
    document.getElementById('password-addon')?.addEventListener('click', function () {
        var passwordInput = document.getElementById("userpassword");
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
        } else {
            passwordInput.type = "password";
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const passwordInput = document.getElementById('userpassword');
        const unlockBtn = document.getElementById('btn-unlock');
        const lockForm = document.getElementById('lockscreen-form');
        const lockoutAlert = document.getElementById('lockout-alert');
        const timerSpan = document.getElementById('lockout-timer');

        let seconds = parseInt('{{ $effectiveLockout }}', 10) || 0;

        if (seconds <= 0 && timerSpan) {
            seconds = parseInt(timerSpan.textContent.trim(), 10) || 0;
        }

        if (seconds > 0) {
            // Ensure input and button are disabled during lockout
            if (passwordInput) {
                passwordInput.disabled = true;
                passwordInput.setAttribute('disabled', 'disabled');
                passwordInput.classList.add('bg-light');
                passwordInput.blur();
            }
            if (unlockBtn) {
                unlockBtn.disabled = true;
                unlockBtn.setAttribute('disabled', 'disabled');
                unlockBtn.classList.add('disabled');
            }

            // Real-time live countdown
            const timerInterval = setInterval(function () {
                seconds--;

                if (timerSpan) {
                    timerSpan.textContent = seconds;
                }

                if (seconds <= 0) {
                    clearInterval(timerInterval);

                    // Re-enable password input and button automatically
                    if (passwordInput) {
                        passwordInput.disabled = false;
                        passwordInput.removeAttribute('disabled');
                        passwordInput.classList.remove('bg-light');
                        passwordInput.focus();
                    }
                    if (unlockBtn) {
                        unlockBtn.disabled = false;
                        unlockBtn.removeAttribute('disabled');
                        unlockBtn.classList.remove('disabled');
                    }

                    // Remove/hide lockout message automatically without page refresh
                    if (lockoutAlert) {
                        lockoutAlert.style.transition = 'opacity 0.5s ease';
                        lockoutAlert.style.opacity = '0';
                        setTimeout(function () {
                            lockoutAlert.remove();
                        }, 500);
                    }
                }
            }, 1000);

            // Block any form submission attempt during lockout
            if (lockForm) {
                lockForm.addEventListener('submit', function (e) {
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
