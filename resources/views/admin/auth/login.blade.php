@extends('admin.layouts.master-without-nav')

@section('title', 'تسجيل الدخول | نظام الحسيني Al-Husseini')

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
                                <img src="{{ asset('assets/images/alhusseini-icon.jpg') }}" alt="Al-Husseini" height="72" class="rounded-circle shadow-lg mb-2" style="border: 2px solid rgba(212, 175, 55, 0.7); box-shadow: 0 0 20px rgba(0, 80, 255, 0.3);">
                                <span class="fs-24 fw-bold text-white">مجموعة الحسيني <span class="text-warning fs-18 fw-normal">| Al-Husseini</span></span>
                            </a>
                        </div>
                        <p class="mt-2 fs-14 fw-medium text-white-50">بوابة إدارة نظام ومجموعة الحسيني</p>
                    </div>
                </div>
            </div>
            <!-- end row -->

            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card mt-4 card-bg-fill">

                        <div class="card-body p-4">
                            <div class="text-center mt-2">
                                <h5 class="text-primary fw-bold">مرحباً بك في نظام الحسيني !</h5>
                                <p class="text-muted">قم بتسجيل الدخول للمتابعة إلى لوحة تحكم Al-Husseini.</p>
                            </div>

                            @if (session('status'))
                                <div class="alert alert-success alert-border-left alert-dismissible fade show my-3" role="alert">
                                    <i class="ri-check-double-line me-2 align-middle fs-16"></i> {{ session('status') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            @if ($errors->any())
                                <div class="alert alert-danger alert-border-left alert-dismissible fade show my-3" role="alert">
                                    <i class="ri-error-warning-line me-2 align-middle fs-16"></i>
                                    <ul class="mb-0 ps-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif

                            <div class="p-2 mt-2">
                                <form action="{{ route('admin.login.submit') }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label for="email" class="form-label">البريد الإلكتروني</label>
                                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="email" placeholder="أدخل البريد الإلكتروني" value="{{ old('email', 'admin@alhusseini.com') }}" required autofocus>
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <div class="float-end">
                                            <a href="javascript:void(0);" class="text-muted">نسيت كلمة المرور؟</a>
                                        </div>
                                        <label class="form-label" for="password-input">كلمة المرور</label>
                                        <div class="position-relative auth-pass-inputgroup mb-3">
                                            <input type="password" name="password" class="form-control pe-5 password-input @error('password') is-invalid @enderror" placeholder="أدخل كلمة المرور" id="password-input" value="12345678" required>
                                            <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none" type="button" id="password-addon"><i class="ri-eye-fill align-middle"></i></button>
                                            @error('password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remember" value="1" id="auth-remember-check" checked>
                                        <label class="form-check-label" for="auth-remember-check">تذكرني على هذا الجهاز</label>
                                    </div>

                                    <div class="mt-4">
                                        <button class="btn btn-primary w-100" type="submit">
                                            <i class="ri-login-box-line me-1 align-middle"></i> تسجيل الدخول
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <!-- end card body -->
                    </div>
                    <!-- end card -->

                    <div class="mt-4 text-center">
                        <p class="mb-0">ليس لديك حساب بعد؟ <a href="{{ route('admin.register') }}" class="fw-semibold text-primary text-decoration-underline"> إنشاء حساب جديد </a> </p>
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
<script src="{{ asset('assets/libs/particles.js/particles.js') }}"></script>
<script src="{{ asset('assets/js/pages/particles.app.js') }}"></script>
<script src="{{ asset('assets/js/pages/password-addon.init.js') }}"></script>
@endsection
