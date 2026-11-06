{{--
    Komponen "Tambah bukti" (tombol plus -> pilih file / foto langsung).
    Parameter:
      $name      nama field yang dikirim ke server (mis. 'document', 'photo')
      $prefix    awalan nama file hasil kamera (mis. 'izin', 'jurnal')
      $onlyImage true = hanya foto (tanpa PDF)
      $cameraOnly true = wajib foto langsung dari kamera (tanpa pilihan galeri/file)
    Script: js/bukti-upload.js (wajib di-push oleh halaman yang memakai komponen ini)
--}}
@php($onlyImage = $onlyImage ?? false)
@php($cameraOnly = $cameraOnly ?? false)
<div class="bukti" data-bukti data-bukti-tipe="{{ $onlyImage ? 'gambar' : 'semua' }}">
    {{-- Input utama yang dikirim ke server (disembunyikan, diisi lewat tombol plus) --}}
    <input type="file" name="{{ $name }}" class="bukti-input" accept="{{ $onlyImage ? 'image/*' : '.pdf,image/*' }}" data-compress data-camera="manual" data-camera-prefix="{{ $prefix }}" @if($cameraOnly) data-camera-only @endif tabindex="-1" required>
    {{-- Cadangan: kamera bawaan HP kalau kamera browser tidak bisa dipakai --}}
    <input type="file" class="d-none" accept="image/*" capture="environment" data-bukti-capture>

    <div class="dropdown" data-bukti-empty>
        <button type="button" class="bukti-tile" @if($cameraOnly) data-aksi="kamera" @else data-bs-toggle="dropdown" aria-expanded="false" @endif>
            <span class="bukti-plus"><i class="bi bi-plus-lg"></i></span>
            <span class="bukti-tile-text">
                <strong>{{ $cameraOnly ? 'Ambil foto langsung' : 'Tambah bukti' }}</strong>
                <small>{{ $cameraOnly ? 'Wajib dari kamera, tidak bisa dari galeri' : ($onlyImage ? 'Foto JPG/PNG, maks. 5 MB' : 'PDF atau foto, maks. 5 MB') }}</small>
            </span>
        </button>
        @unless($cameraOnly)
        <ul class="dropdown-menu bukti-menu">
            <li>
                <button type="button" class="dropdown-item bukti-opt" data-aksi="file">
                    <span class="bukti-opt-ic ic-file"><i class="bi bi-folder2-open"></i></span>
                    <span><strong>{{ $onlyImage ? 'Pilih Foto' : 'Pilih File' }}</strong><small>{{ $onlyImage ? 'Foto dari galeri perangkat' : 'PDF atau foto dari perangkat' }}</small></span>
                </button>
            </li>
            <li>
                <button type="button" class="dropdown-item bukti-opt" data-aksi="kamera">
                    <span class="bukti-opt-ic ic-cam"><i class="bi bi-camera"></i></span>
                    <span><strong>Foto Langsung</strong><small>Ambil lewat kamera sekarang</small></span>
                </button>
            </li>
        </ul>
        @endunless
    </div>

    <div class="bukti-chip d-none" data-bukti-chip>
        <div class="bukti-thumb" data-bukti-thumb></div>
        <div class="bukti-meta">
            <div class="nm" data-bukti-name></div>
            <div class="inf" data-bukti-info></div>
        </div>
        <button type="button" class="bukti-remove" data-bukti-remove aria-label="Hapus bukti"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="bukti-error d-none" data-bukti-error></div>
</div>
