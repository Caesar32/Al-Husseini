@extends('admin.layouts.master-without-nav')

@section('title', 'الصفحة غير موجودة 404 | نظام الحسيني')

@section('content')
<div class="auth-page-wrapper pt-5">
    <div class="auth-one-bg-position auth-one-bg" id="auth-particles">
        <div class="bg-overlay"></div>
        <div class="shape">
            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1440 120">
                <path d="M 0,36 C 144,53.6 432,123.2 720,124 C 1008,124.8 1296,56.8 1440,40L1440 140L0 140z"></path>
            </svg>
        </div>
    </div>

    <div class="auth-page-content">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-center pt-4">
                        <div class="">
                            <img src="{{ asset('assets/images/error.svg') }}" alt="" class="error-basic-img-1" height="210">
                        </div>
                        <h1 class="text-uppercase text-muted display-1">404</h1>
                        <h3 class="text-uppercase">عذراً! الصفحة غير موجودة 😭</h3>
                        <p class="text-muted w-lg-50 mx-auto">الصفحة التي تبحث عنها قد تم نقلها أو حذفها أو لم تعد متاحة حالياً داخل نظام الحسيني.</p>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-success"><i class="mdi mdi-home me-1"></i> العودة إلى لوحة تحكم الحسيني</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-center">
                        <p class="mb-0 text-muted">&copy; {{ date('Y') }} مجموعة الحسيني - Al-Husseini. جميع الحقوق محفوظة.</p>
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
@endsection
