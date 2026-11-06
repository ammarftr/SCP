{{-- Input jam berbasis sesi (jam ke-), mengikuti Hari yang dipilih pada form yang sama.
     Server mengubah sesi menjadi jam_mulai & jam_selesai (HH:MM); data di database tidak berubah.
     Parameter opsional: $sesiMulai, $sesiSelesai (nilai awal). --}}
<div class="jam-group mb-3" data-sesi-mulai="{{ $sesiMulai ?? '' }}" data-sesi-selesai="{{ $sesiSelesai ?? '' }}">
    <div class="row g-2">
        <div class="col-6">
            <label class="form-label small fw-semibold">Mulai Jam ke-</label>
            <select name="sesi_mulai" class="form-select sel-mulai" required></select>
        </div>
        <div class="col-6">
            <label class="form-label small fw-semibold">Sampai Jam ke-</label>
            <select name="sesi_selesai" class="form-select sel-selesai" required></select>
        </div>
    </div>
    <small class="text-muted d-block mt-2 durasi-info"></small>
</div>
