# SCP (School Class Progress)

Aplikasi presensi, jurnal mengajar, dan perizinan siswa untuk SMK Negeri 1 Bantul, dengan jadwal blok dua mingguan (Blok 1 dan Blok 2). Dibuat sebagai tugas Uji Kompetensi Keahlian (UKK) Rekayasa Perangkat Lunak. Tampilannya mengutamakan HP dan bisa dipasang sebagai PWA.

## Teknologi
- PHP 8.4 atau lebih baru, Laravel 13
- MySQL / MariaDB
- Bootstrap 5, Bootstrap Icons, Tom Select, JavaScript biasa
- PWA (service worker dan manifest)

## Peran pengguna
| Peran | Fungsi utama |
|---|---|
| Waka Kurikulum | Data master (akun, kelas, mapel, ruangan), impor siswa, kenaikan kelas, ganti blok aktif, kunci jadwal per blok, rekap jurnal, daftar guru yang belum mengisi jurnal |
| Guru (termasuk Guru BK) | Input jadwal sendiri, isi jurnal dengan foto dan presensi, peringatan jurnal terlambat |
| Wali Kelas / Guru BK (Tim Kesiswaan) | Rekap absensi, verifikasi izin/sakit, dan laporan izin harian untuk kelas yang diampu |
| Siswa / Orang Tua | Satu portal untuk melihat rekap kehadiran dan mengajukan izin/sakit beserta bukti |

Semua peran punya halaman profil untuk mengganti foto, kata sandi, dan email. Email dipakai untuk lupa kata sandi lewat kode OTP.

## Aturan bisnis
1. Anti-tabrakan jadwal. Jadwal ditolak bila ruang, kelas, atau guru beririsan waktu (`mulai_A < selesai_B AND selesai_A > mulai_B`) pada hari dan blok yang sama. Dicek saat tambah dan ubah (`JadwalPelajaranController`).
2. Putaran blok. Waka mengatur blok aktif, dan dashboard guru, jurnal, serta peringatan mengikuti blok aktif.
3. Kunci jadwal. Dikunci per blok oleh Waka, dan pemeriksaannya ada di server sehingga tetap aman walau tombol disembunyikan. Ganti blok aktif dan kunci adalah dua aksi terpisah. `is_schedule_locked` bernilai true bila ada blok yang terkunci.
4. Sinkronisasi izin. Izin berstatus Pending saat diajukan. Setelah Approved, presensi diisi otomatis (Sakit/Izin) ketika guru membuat jurnal pada tanggal yang tercakup izin (dihitung di server pada `JurnalController@store`). Bila izin disetujui setelah jurnal terlanjur mencatat Alfa, statusnya dikoreksi dan status asal disimpan sebagai jejak audit.
5. Peringatan jurnal. Saat dashboard dimuat, jadwal hari ini yang `jam_selesai`-nya lewat lebih dari 30 menit tanpa jurnal ditandai. Tidak memakai cron job.
6. Kenaikan kelas. Diproses Waka dari halaman Kenaikan Kelas, dengan aturan sebagai berikut.
   - Nama kelas mengikuti pola tingkat + jurusan + nomor. Kelas X RPL 1 naik ke XI RPL 1, XI RPL 1 naik ke XII RPL 1, dan XII RPL 1 lulus. Siswa tidak diacak.
   - Hanya siswa berstatus Aktif yang diproses. Siswa yang lulus berstatus Lulus, `id_kelas`-nya dikosongkan, dan akunnya dinonaktifkan. Akun mereka dipisah ke bagian Akun Alumni di Manajemen Data.
   - Kelas tujuan yang belum ada dibuat otomatis. Wali kelas tetap melekat pada kelasnya.
   - Waka bisa mengecualikan siswa tertentu (tinggal kelas, pindah). Seluruh proses berjalan dalam satu transaksi.
   - Setiap proses tercatat, dan proses terakhir bisa dibatalkan. Siswa yang datanya sudah diubah manual sesudah proses dilewati saat pembatalan.
   - Data presensi dan jurnal lama tidak dihapus.
7. Impor siswa. Waka menyalin kolom NIS dan nama dari Excel, memilih kelas, memeriksa pratinjau, lalu menyimpan. Akun dibuat dengan username berupa NIS dan kata sandi awal `12345678`. NIS yang dobel atau sudah terdaftar dilewati dan ditampilkan alasannya. Maksimal 1000 baris per impor.

Validasi unggah: foto jurnal JPG/PNG maksimal 5 MB, dokumen izin PDF/JPG/PNG maksimal 5 MB. Berkas disimpan di `storage/app/private` dan hanya bisa dibuka lewat rute yang memeriksa hak akses.

## Tampilan HP
- Navigasi bawah untuk HP dan sidebar untuk desktop.
- Pada layar kurang dari 768 px, tabel data ditampilkan sebagai kartu per baris sehingga tidak perlu digeser ke samping. Logikanya ada di `public/js/tabel-responsif.js` dan `public/css/app.css`. Tabel jadwal Waka tetap berbentuk tabel.

## Pemetaan skema soal ke tabel proyek
Nama tabel memakai bahasa Indonesia, sedangkan struktur dan relasinya mengikuti blueprint soal.

| Blueprint | Tabel proyek | Catatan |
|---|---|---|
| `roles` | `role` | `role_name` menjadi `nama_role` |
| `users` | `users` | `fullname` menjadi `nama_lengkap`, `password` menjadi `password_hash`, `role_id` menjadi `id_role`. Siswa dan orang tua berbagi satu akun, jadi `parent_id` tidak dipakai |
| `classes` | `kelas` | Kunci utama `id_kelas` (nama kelas) |
| `rooms` | `ruangan` | `id_ruangan`, `nama_ruangan` |
| `schedules` | `jadwal_pelajaran` | `teacher_id` menjadi `id_guru`, `class_id` menjadi `id_kelas`, `room_id` menjadi `id_ruangan_default`, `day_of_week` menjadi `hari`, `start/end_time` menjadi `jam_mulai/jam_selesai`, `cycle_type` bernilai Blok 1 atau Blok 2 |
| `journals` | `journals` | Sama |
| `attendances` | `attendances` | Kolom `date` ada dan diisi tanggal jurnal |
| `leave_requests` | `leave_requests` | Ditambah `jenis` (Sakit/Izin) dan `alasan_penolakan` |
| `system_configurations` | `system_configurations` | `is_schedule_locked` dipertahankan, kunci per blok ada di `is_locked_blok_1/2` |

Tabel tambahan:
- `siswa`, `guru`, `mata_pelajaran`, `jurusan`: data master.
- `tahun_ajaran`: tetap ada untuk relasi jadwal, tetapi aplikasi tidak lagi memakai konsep semester. Hanya satu baris bawaan yang dipakai.
- `kode_otp`: kode untuk lupa kata sandi lewat email.
- `kenaikan_kelas` dan `kenaikan_kelas_detail`: riwayat proses kenaikan kelas dan kondisi tiap siswa sebelum dan sesudahnya, dipakai untuk pembatalan. Tabel ini dibuat oleh migrasi, dan dibuat otomatis saat halaman Kenaikan Kelas pertama kali dibuka bila belum ada.

## Instalasi lokal
```bash
git clone https://github.com/ammarftr/SCP.git
cd SCP
composer install
cp .env.example .env        # lalu isi DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
Buka `http://localhost:8000`.

Akun uji coba dari seeder (hanya untuk lokal, kata sandi `12345678`): `waka`, `guru`, `walkel`, `bk`, `siswa`. Hapus atau ganti kata sandi akun ini di server publik.

Email keluar memakai `MAIL_MAILER=log` secara bawaan, jadi kode OTP hanya tertulis di `storage/logs`. Isi pengaturan `MAIL_*` di `.env` bila ingin mengirim email sungguhan.

## Deploy ke hosting (cPanel)
1. Unggah proyek, lalu arahkan document root ke folder `public`.
2. Isi `.env` dengan `APP_ENV=production`, `APP_DEBUG=false`, kunci aplikasi, dan koneksi database hosting.
3. Pastikan `storage` dan `bootstrap/cache` bisa ditulis.
4. Impor database atau jalankan migrasi. Tanpa akses terminal, tabel dapat dibuat lewat phpMyAdmin.
5. Setelah memperbarui CSS atau JS, muat ulang browser dengan Ctrl+F5. Pengguna PWA perlu menutup dan membuka kembali aplikasinya agar berkas baru terambil.

## Pengujian manual yang disarankan
1. Login sebagai guru, input jadwal, lalu input jadwal yang bentrok. Jadwal kedua harus ditolak.
2. Login sebagai siswa, ajukan izin dengan bukti, lalu login sebagai wali kelas dan setujui.
3. Login sebagai guru, buat jurnal pada hari izin. Siswa tadi otomatis berstatus Izin/Sakit.
4. Biarkan satu jadwal tanpa jurnal lebih dari 30 menit setelah selesai. Peringatan muncul di dashboard guru.
5. Login sebagai Waka, kunci Blok 1. Guru tidak bisa mengubah atau menghapus jadwal Blok 1.
6. Login sebagai Waka, buka Impor Siswa, tempel data NIS dan nama, periksa pratinjau, lalu simpan. Siswa muncul di tab Siswa pada kelas yang dipilih.
7. Login sebagai Waka, buka Kenaikan Kelas, jalankan proses pada data uji, periksa kelas siswa, lalu batalkan. Data kembali seperti semula.
8. Buka Manajemen Data di HP (lebar sekitar 375 px). Tabel tampil sebagai kartu tanpa geser ke samping.

## Batasan yang perlu diketahui
- Menghapus akun juga menghapus data siswa atau guru terkait, termasuk riwayat presensi dan jurnalnya. Akun alumni sebaiknya dibiarkan nonaktif kecuali datanya memang tidak dibutuhkan lagi.
- Kata sandi awal akun hasil impor sama untuk semua siswa, dan belum ada kewajiban menggantinya saat login pertama.
- Daftar akun di Manajemen Data dimuat sekaligus lalu dipecah di browser, sehingga bisa melambat bila jumlah akun mencapai ribuan.
- Belum ada tes otomatis. Pengujian dilakukan manual seperti daftar di atas.

## Struktur singkat
```
app/Http/Controllers   logika per fitur (Jadwal, Jurnal, Kesiswaan, MasterData, KenaikanKelas, ImporSiswa, ...)
app/Http/Middleware    PastikanPeran (pembatas akses per peran)
app/Mail               email OTP dan notifikasi akun
database/migrations    skema tabel
resources/views        tampilan Blade per peran
public/js, public/css  aset, termasuk tabel-responsif.js; sw.js dan manifest.json untuk PWA
```
