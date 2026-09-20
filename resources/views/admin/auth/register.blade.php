@extends('admin.layouts.master-without-nav')

@section('title', 'إنشاء حساب جديد | نظام الحسيني Al-Husseini')

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

            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 col-xl-5">
                    <div class="card mt-4 card-bg-fill">

                        <div class="card-body p-4">
                            <div class="text-center mt-2">
                                <h5 class="text-primary fw-bold">إنشاء حساب في نظام الحسيني</h5>
                                <p class="text-muted">احصل على حسابك الخاص في لوحة تحكم Al-Husseini الآن</p>
                            </div>
                            <div class="p-2 mt-4">
                                <form action="{{ route('admin.dashboard') }}" method="GET">
                                    <div class="mb-3">
                                        <label for="useremail" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="useremail" placeholder="أدخل البريد الإلكتروني" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="username" class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="username" placeholder="أدخل اسم المستخدم" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="password-input">كلمة المرور <span class="text-danger">*</span></label>
                                        <div class="position-relative auth-pass-inputgroup">
                                            <input type="password" class="form-control pe-5 password-input" placeholder="أدخل كلمة المرور" id="password-input" required>
                                            <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none" type="button" id="password-addon"><i class="ri-eye-fill align-middle"></i></button>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <p class="mb-0 fs-12 text-muted fst-italic">بالتسجيل أنت توافق على <a href="javascript:void(0);" class="text-primary text-decoration-underline fst-normal fw-medium">شروط الاستخدام</a></p>
                                    </div>

                                    <div class="mt-4">
                                        <button class="btn btn-success w-100" type="submit">تسجيل الحساب</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-center">
                        <p class="mb-0">لديك حساب بالفعل؟ <a href="{{ route('admin.login') }}" class="fw-semibold text-primary text-decoration-underline"> تسجيل الدخول </a> </p>
                    </div>

                </div>
            </div>
        </div>
    </div>

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
</div>
@endsection

@section('script')
<script src="{{ asset('assets/libs/particles.js/particles.js') }}"></script>
<script src="{{ asset('assets/js/pages/particles.app.js') }}"></script>
<script src="{{ asset('assets/js/pages/password-addon.init.js') }}"></script>
@endsection
