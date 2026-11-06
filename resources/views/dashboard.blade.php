<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - UKK SCP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <div class="card shadow-sm p-5">
                    <h1 class="mb-3">Aplikasi UKK SCP</h1>
                    <p class="text-muted mb-4">Kemajuan Kelas Sekolah</p>
                    
                    <div class="d-grid gap-3 col-6 mx-auto">
                        <a href="{{ route('jadwal.index') }}" class="btn btn-primary btn-lg">Kelola Jadwal Pelajaran</a>
                        <a href="{{ route('jurnal.index') }}" class="btn btn-success btn-lg">Modul Jurnal Mengajar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>