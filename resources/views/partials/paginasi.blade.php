{{--
    Info jumlah + tombol halaman untuk daftar yang dipaginasi dari server.
    Pakai:  @include('partials.paginasi', ['paginator' => $daftar, 'label' => 'jurnal'])
--}}
@if ($paginator->total() > 0 && ($paginator->hasPages() || ($tampilInfo ?? false)))
    <div class="scp-paginasi d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 px-3 px-md-0 py-3">
        <small class="text-muted">
            Menampilkan {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} dari {{ $paginator->total() }} {{ $label ?? 'data' }}
        </small>
        {{ $paginator->links() }}
    </div>
@endif
