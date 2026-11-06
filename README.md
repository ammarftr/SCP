# SCP — Sistem Anti Tabrak Jadwal

Aplikasi web (PWA) untuk mengelola jadwal pelajaran, jurnal mengajar, presensi siswa, dan perizinan siswa di **SMK Negeri 1 Bantul**. Sistem mencegah jadwal bentrok pada **ruang, jam, dan guru**.

Proyek ini dibuat untuk **Uji Kompetensi Keahlian (UKK) Rekayasa Perangkat Lunak**.

- **Demo:** https://scpammar.my.id
- **Repo:** https://github.com/ammarftr/SCP

## Fitur

| Peran | Fitur utama |
|---|---|
| **Waka Kurikulum** | Kelola data master (guru, siswa, kelas, ruangan, mata pelajaran, wali kelas), susun jadwal tanpa bentrok, rekap jurnal |
| **Guru** | Lihat jadwal, isi jurnal mengajar, presensi siswa, ubah status kehadiran |
| **Wali Kelas** | Verifikasi izin siswa, rekap absensi, laporan izin |
| **Guru BK** | Mengajar seperti guru mapel, verifikasi izin (wajib mengisi alasan saat menolak) |
| **Siswa** | Ajukan izin atau sakit dengan bukti foto, pantau status pengajuan |

Fitur umum: login, lupa kata sandi dengan kode OTP lewat email, foto profil, instalasi sebagai PWA, dan tampilan penuh bahasa Indonesia.

## Teknologi

- PHP 8.3+, Laravel 13
- MySQL / MariaDB
- Blade, Tailwind CSS 4, Vite
- PWA (manifest dan service worker)

## Instalasi

Prasyarat: PHP 8.3+, Composer, Node.js, MySQL.

```bash
git clone https://github.com/ammarftr/SCP.git
cd SCP

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Buat database kosong, lalu atur koneksinya di `.env`:

```
DB_DATABASE=nama_database
DB_USERNAME=root
DB_PASSWORD=
```

Jalankan migrasi dan data contoh, lalu aplikasi:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

Buka http://127.0.0.1:8000.

## Akun demo

Kata sandi semua akun uji coba: `12345678`

| Peran | Username |
|---|---|
| Waka Kurikulum | `waka` |
| Guru | `guru` |
| Wali Kelas | `walkel` |
| Guru BK | `bk` |
| Siswa | `siswa` |

## Struktur folder

```
app/Http/Controllers   logika tiap fitur
app/Models             model Eloquent
database/migrations    struktur database
database/seeders       data contoh dan akun demo
resources/views        tampilan Blade
routes/web.php         daftar rute dan pembatasan peran
public                 aset, manifest PWA, service worker
```

## Pengembang

Ammar Fathurrahman — SMK Negeri 1 Bantul
GitHub: [@ammarftr](https://github.com/ammarftr)
