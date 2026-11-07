{{-- Page module: public/assets/js/admin/pos/pos.js (config on [data-page="pos"]); money math in pos/math.js. --}}
{{-- The server caches JS for a week: the file time in the URL makes every deploy reach cashiers at once. --}}
<script src="{{ asset('assets/js/admin/pos/math.js') }}?v={{ filemtime(public_path('assets/js/admin/pos/math.js')) }}"></script>
<script src="{{ asset('assets/js/admin/pos/pos.js') }}?v={{ filemtime(public_path('assets/js/admin/pos/pos.js')) }}"></script>
