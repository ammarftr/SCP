# SCP (School Class Progress)

Aplikasi web untuk mencatat presensi, jurnal mengajar, dan izin siswa di SMK Negeri 1 Bantul. Jadwalnya memakai sistem blok dua mingguan (Blok 1 dan Blok 2). Saya buat untuk tugas UKK Rekayasa Perangkat Lunak. Tampilannya saya buat nyaman dibuka dari HP dan bisa dipasang sebagai PWA.

## Teknologi
PHP 8.3 ke atas, Laravel 13, MySQL/MariaDB, Bootstrap 5, Bootstrap Icons, Tom Select, dan JavaScript biasa. Service worker dan manifest dipakai untuk PWA.

Tidak perlu Node.js atau npm. File `package.json` dan `vite.config.js` itu bawaan Laravel dan tidak terpakai, karena semua CSS dan JS ada di `public/css` dan `public/js`.

## Siapa saja penggunanya
- **Waka Kurikulum**: mengelola data master (akun, kelas, mapel, ruangan), impor siswa, kenaikan kelas, mengganti blok aktif, mengunci jadwal per blok, dan melihat rekap jurnal serta guru yang belum mengisi jurnal.
- **Guru** (termasuk Guru BK): menginput jadwal sendiri, mengisi jurnal lengkap dengan foto dan presensi, dan mendapat peringatan kalau jurnal terlambat.
- **Wali Kelas dan Guru BK** (tim kesiswaan): melihat rekap absensi, memverifikasi izin dan sakit, dan membuka laporan izin harian untuk kelas yang mereka ampu.
- **Siswa / orang tua**: satu akun untuk melihat rekap kehadiran dan mengajukan izin atau sakit beserta buktinya.

Semua peran punya halaman profil untuk ganti foto, kata sandi, dan email. Email dipakai untuk fitur lupa kata sandi lewat kode OTP.

## Aturan yang dipakai di aplikasi
1. **Jadwal tidak boleh bentrok.** Jadwal ditolak kalau ruang, kelas, atau gurunya beririsan waktu di hari dan blok yang sama (`mulai_A < selesai_B AND selesai_A > mulai_B`). Dicek saat tambah dan ubah, kodenya di `JadwalPelajaranController`.
2. **Blok aktif.** Waka yang menentukan blok aktif. Dashboard guru, jurnal, dan peringatan mengikuti blok itu.
3. **Kunci jadwal.** Waka bisa mengunci jadwal per blok. Pengecekannya di server, jadi tetap aman walaupun tombolnya disembunyikan. Mengganti blok aktif dan mengunci adalah dua aksi yang terpisah. `is_schedule_locked` bernilai true kalau ada blok yang terkunci.
4. **Izin dan presensi.** Izin yang baru diajukan berstatus Pending. Kalau sudah Approved, presensi siswa terisi otomatis (Sakit/Izin) saat guru membuat jurnal di tanggal yang tercakup izin itu (dihitung di `JurnalController@store`). Kalau izin baru disetujui setelah jurnal terlanjur mencatat Alfa, statusnya dikoreksi dan status lamanya disimpan sebagai jejak.
5. **Peringatan jurnal.** Saat dashboard dibuka, jadwal hari ini yang sudah lewat lebih dari 30 menit dari `jam_selesai` dan belum ada jurnalnya akan ditandai. Tidak pakai cron job.
6. **Kenaikan kelas.** Dijalankan Waka dari halaman Kenaikan Kelas:
   - Nama kelas mengikuti pola tingkat + jurusan + nomor. X RPL 1 naik ke XI RPL 1, XI RPL 1 naik ke XII RPL 1, dan XII RPL 1 lulus. Siswa tidak diacak.
   - Yang diproses hanya siswa berstatus Aktif. Siswa yang lulus berstatus Lulus, `id_kelas` dikosongkan, akunnya dinonaktifkan, dan masuk ke bagian Akun Alumni di Manajemen Data.
   - Kelas tujuan yang belum ada dibuat otomatis. Wali kelas tetap menempel di kelasnya.
   - Waka bisa mengecualikan siswa tertentu, misalnya yang tinggal kelas atau pindah. Seluruh proses jalan dalam satu transaksi.
   - Setiap proses dicatat, dan proses terakhir bisa dibatalkan. Siswa yang datanya sudah diubah manual setelah proses akan dilewati saat pembatalan.
   - Data presensi dan jurnal lama tidak dihapus.
7. **Impor siswa.** Waka menyalin kolom NIS dan nama dari Excel, memilih kelas, melihat pratinjau, lalu menyimpan. Username akun berupa NIS dan kata sandi awalnya `12345678`. NIS yang dobel atau sudah terdaftar dilewati dan alasannya ditampilkan. Maksimal 1000 baris sekali impor.

Untuk unggahan, foto jurnal berformat JPG/PNG dan dokumen izin berformat PDF/JPG/PNG, masing-masing maksimal 5 MB. Berkasnya disimpan di `storage/app/private` dan hanya bisa dibuka lewat rute yang mengecek hak akses.

## Tampilan di HP
Di HP ada navigasi bawah, sedangkan di desktop memakai sidebar. Pada layar di bawah 768 px, tabel data berubah jadi kartu per baris supaya tidak perlu digeser ke samping. Logikanya ada di `public/js/tabel-responsif.js` dan `public/css/app.css`. Tabel jadwal Waka sengaja tetap berbentuk tabel.

## Pemetaan skema soal ke tabel proyek
Nama tabel saya buat dalam bahasa Indonesia, tapi struktur dan relasinya tetap mengikuti blueprint dari soal.

| Blueprint | Tabel proyek | Catatan |
|---|---|---|
| `roles` | `role` | `role_name` jadi `nama_role` |
| `users` | `users` | `fullname` jadi `nama_lengkap`, `password` jadi `password_hash`, `role_id` jadi `id_role`. Siswa dan orang tua pakai satu akun, jadi `parent_id` tidak dipakai |
| `classes` | `kelas` | Kunci utamanya `id_kelas` (nama kelas) |
| `rooms` | `ruangan` | `id_ruangan`, `nama_ruangan` |
| `schedules` | `jadwal_pelajaran` | `teacher_id` jadi `id_guru`, `class_id` jadi `id_kelas`, `room_id` jadi `id_ruangan_default`, `day_of_week` jadi `hari`, `start/end_time` jadi `jam_mulai/jam_selesai`, `cycle_type` berisi Blok 1 atau Blok 2 |
| `journals` | `journals` | Sama |
| `attendances` | `attendances` | Ada kolom `date` yang diisi tanggal jurnal |
| `leave_requests` | `leave_requests` | Ditambah `jenis` (Sakit/Izin) dan `alasan_penolakan` |
| `system_configurations` | `system_configurations` | `is_schedule_locked` tetap ada, kunci per blok di `is_locked_blok_1/2` |

Tabel tambahan di luar blueprint:
- `siswa`, `guru`, `mata_pelajaran`, `jurusan`: data master.
- `tahun_ajaran`: masih ada karena dipakai relasi jadwal, tapi aplikasi tidak lagi memakai konsep semester. Yang dipakai hanya satu baris bawaan.
- `kode_otp`: kode untuk lupa kata sandi lewat email.
- `kenaikan_kelas` dan `kenaikan_kelas_detail`: riwayat proses kenaikan kelas dan kondisi tiap siswa sebelum dan sesudahnya, dipakai untuk fitur pembatalan. Tabelnya dibuat lewat migrasi. Kalau belum ada, tabel dibuat otomatis saat halaman Kenaikan Kelas pertama dibuka, dengan syarat user database punya izin `CREATE`.

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
Lalu buka `http://localhost:8000`.

Seeder membuat akun uji coba `waka`, `guru`, `walkel`, `bk`, dan `siswa`, semuanya dengan kata sandi `12345678`. Akun ini hanya untuk lokal. Di server publik, hapus atau ganti kata sandinya.

Secara bawaan `MAIL_MAILER=log`, jadi kode OTP hanya muncul di `storage/logs`. Kalau mau kirim email sungguhan, isi pengaturan `MAIL_*` di `.env`.

## Perintah artisan tambahan
**`php artisan bk:impor-kelas`** menetapkan Guru BK untuk tiap kelas (kolom `kelas.id_bk`) berdasarkan `database/data/wali_bk_2026_2027.json`. Nama kelas dicocokkan tanpa memedulikan spasi, dan Guru BK dicocokkan dengan nama akun yang berperan Guru BK. Pakai `--simulasi` kalau hanya ingin melihat hasil pencocokan tanpa menyimpan, atau `--berkas=` untuk memakai file JSON lain. Kelas atau akun yang belum ada akan muncul sebagai peringatan.

**`php artisan berkas:amankan`** memindahkan surat izin dan foto jurnal lama dari `public/uploads` ke `storage/app/private`, lalu memperbarui path-nya di database. Cukup dijalankan sekali kalau memperbarui dari versi lama.

## Deploy ke hosting (cPanel)
1. Unggah proyek dan arahkan document root ke folder `public`.
2. Buat `.env` di server dengan `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` (bisa dibuat dengan `php artisan key:generate --show`), dan koneksi database hosting. Kalau pakai HTTPS, tambahkan `SESSION_SECURE_COOKIE=true`. Jangan pernah meng-commit `.env` ke GitHub.
3. Pastikan folder `storage` dan `bootstrap/cache` bisa ditulis.
4. Buat tabelnya dengan `php artisan migrate --force`. Kalau tidak ada akses terminal, impor database lewat phpMyAdmin. Ulangi langkah ini setiap ada migrasi baru, misalnya untuk tabel kenaikan kelas.
5. Setelah mengubah CSS atau JS, naikkan nomor `VERSI` di `public/sw.js`, lalu muat ulang browser dengan Ctrl+F5. Pengguna PWA perlu menutup dan membuka lagi aplikasinya supaya berkas baru terambil.

## Memperbarui repositori GitHub
```bash
git status
git add -A
git status          # pastikan tidak ada .env, vendor, atau storage/logs
git commit -m "Pesan perubahan"
git push origin main
```
File yang tidak boleh ikut ke GitHub sudah masuk `.gitignore`, yaitu `.env`, `vendor`, `storage/logs`, unggahan pengguna di `public/uploads` dan `storage/app/private`, serta salinan hosting di `public/home`.

## Pengujian manual
1. Login sebagai guru, input jadwal, lalu input jadwal yang bentrok. Jadwal kedua harus ditolak.
2. Login sebagai siswa, ajukan izin dengan bukti, lalu login sebagai wali kelas dan setujui.
3. Login sebagai guru dan buat jurnal di hari izin tadi. Siswa tersebut otomatis berstatus Izin/Sakit.
4. Biarkan satu jadwal tanpa jurnal lebih dari 30 menit setelah selesai. Peringatan muncul di dashboard guru.
5. Login sebagai Waka dan kunci Blok 1. Guru tidak bisa mengubah atau menghapus jadwal Blok 1.
6. Login sebagai Waka, buka Impor Siswa, tempel data NIS dan nama, cek pratinjau, lalu simpan. Siswa muncul di tab Siswa pada kelas yang dipilih.
7. Login sebagai Waka, buka Kenaikan Kelas, jalankan proses pada data uji, cek kelas siswa, lalu batalkan. Datanya kembali seperti semula.
8. Buka Manajemen Data di HP (lebar sekitar 375 px). Tabel tampil sebagai kartu dan tidak perlu digeser.

## Batasan
- Menghapus akun ikut menghapus data siswa atau guru terkait, termasuk riwayat presensi dan jurnalnya. Biarkan akun alumni tetap nonaktif kecuali datanya memang sudah tidak dibutuhkan.
- Kata sandi awal akun hasil impor sama untuk semua siswa, dan belum ada kewajiban menggantinya saat login pertama.
- Daftar akun di Manajemen Data dimuat sekaligus lalu dipecah di browser, jadi bisa melambat kalau akunnya sudah ribuan.
- Belum ada tes otomatis. Pengujian masih manual seperti daftar di atas.
- File `database/data/wali_bk_2026_2027.json` berisi nama guru. Kalau repo dibuat publik dan data itu tidak boleh disebar, hapus atau ganti isinya.

## Struktur folder
```
app/Http/Controllers   logika tiap fitur (Jadwal, Jurnal, Kesiswaan, MasterData, KenaikanKelas, ImporSiswa, dll.)
app/Http/Middleware    PastikanPeran, pembatas akses per peran
app/Console/Commands   perintah artisan (bk:impor-kelas, berkas:amankan)
app/Mail               email OTP dan notifikasi akun
database/migrations    skema tabel
database/data          data awal berformat JSON (wali kelas dan Guru BK)
resources/views        tampilan Blade per peran
public/js, public/css  aset, termasuk tabel-responsif.js; sw.js dan manifest.json untuk PWA
```
