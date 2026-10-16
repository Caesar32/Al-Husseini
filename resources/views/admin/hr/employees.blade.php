@extends('admin.layouts.master')

@section('title', 'دليل الموظفين | نظام الحسيني الإداري')

@section('content')
@php
    $pageConfig = ['routes' => [
        'employees' => route('admin.hr.employees'),
        'employee' => route('admin.hr.employees.show', ['employee' => '__ID__']),
    ]];
@endphp
<div data-page="hr.employees" data-config='@json($pageConfig)'>
    @include('admin.layouts.partials.page-title', ['pagetitle' => 'الموارد البشرية', 'title' => 'دليل وشؤون الموظفين'])

    {{-- 1. بطاقات الإحصائيات العلوية --}}
    @include('admin.hr.partials.employees.stats')

    {{-- 2. شريط البحث والتصفية والأزرار --}}
    @include('admin.hr.partials.employees.filters')

    {{-- 3. جدول عرض الموظفين --}}
    @include('admin.hr.partials.employees.table')

    {{-- 4. بطاقات العرض الشبكي (Grid View) --}}
    @include('admin.hr.partials.employees.grid')

    {{-- 5. نافذة إضافة وتعديل موظف --}}
    @include('admin.hr.partials.employees.modal-form')

    {{-- 6. نافذة الملف التعريفي التفصيلي للموظف --}}
    @include('admin.hr.partials.employees.modal-profile')
</div>
@endsection

@section('script')
    {{-- 7. محرك الجافاسكريبت والربط البرمجي --}}
    @include('admin.hr.partials.employees.scripts')
@endsection
