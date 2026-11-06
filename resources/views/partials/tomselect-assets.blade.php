{{-- Aset dropdown yang bisa dicari (Tom Select). Di-include lewat @include di dalam @section('content'). --}}
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .ts-wrapper.form-select, .ts-wrapper.single .ts-control { min-height: 38px; }
    .ts-dropdown .ts-dropdown-content { max-height: 260px; }
    .jadwal-filter-bar { background:#F8F9FB; border:1px solid #E9ECF1; border-radius:10px; padding:.85rem; }
    .jadwal-filter-bar .form-label { margin-bottom:.25rem; }
</style>
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
@endpush
