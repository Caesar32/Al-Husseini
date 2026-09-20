@extends('admin.layouts.master')

@section('title', 'صفحة جديدة | نظام الحسيني Al-Husseini')

@section('content')
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'نظام الحسيني', 'title' => 'صفحة البداية - Al-Husseini'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">نظام الحسيني - صفحة البداية (Al-Husseini Starter)</h4>
                    <div class="flex-shrink-0">
                        <button type="button" class="btn btn-soft-primary btn-sm">
                            <i class="ri-add-line align-middle me-1"></i> زر إجراء
                        </button>
                    </div>
                </div><!-- end card header -->

                <div class="card-body">
                    <p class="text-muted">
                        هذه صفحة نموذجية خالية ونظيفة (Starter Page)، تم تجهيزها لتبدأ في بناء أي قسم أو موديول جديد داخل لوحة تحكم نظام الحسيني (Al-Husseini) بكل سهولة واحترافية.
                    </p>
                    <div class="alert alert-primary alert-dismissible alert-label-icon label-arrow fade show material-shadow" role="alert">
                        <i class="ri-user-smile-line label-icon"></i><strong>مرحباً بك في نظام الحسيني!</strong> القالب مركب ومقسم بالكامل بنظام Blade، ومستعد لإضافة الجداول والنماذج الخاصة بك.
                    </div>
                </div><!-- end card-body -->
            </div><!-- end card -->
        </div><!-- end col -->
    </div><!-- end row -->
@endsection
