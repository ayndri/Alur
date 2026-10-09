# Alur

**Papan kanban untuk tim kecil yang berani bilang "penuh".** Setiap kolom punya batas kartu (WIP limit)
yang benar-benar ditegakkan, dan setiap perpindahan kartu dicatat. Dari catatan itu Alur menjawab
pertanyaan yang biasanya cuma dirasakan: kartu ini lama di mana, kenapa dikembalikan, dan kapan sisa
pekerjaan kemungkinan selesai.

> _A team kanban board with enforced WIP limits. Every card move is recorded, and the history answers
> where the time actually went (in QA, in rework loops, or waiting to be merged) and forecasts when the
> remaining work will likely be done with a Monte Carlo simulation. Laravel 12 on Postgres (Neon),
> deployed as a single serverless function on Vercel. UI and content are in Indonesian._

---

## Kenapa ini bukan CRUD kanban

### 1. Batas WIP yang tidak bisa jebol

Batas yang hanya dicek di browser mudah ditembus: dua orang melihat satu slot kosong, keduanya menyeret
kartu. Di Alur, **semua perubahan papan lewat satu kelas, [`Board`](app/Services/Board.php), dan setiap
operasinya mengunci baris proyek dulu** (`SELECT … FOR UPDATE` di dalam transaksi). Satu titik kunci juga
berarti urutan kunci selalu sama, jadi tidak ada deadlock antara "buat kartu" dan "pindah kartu".

Buktinya ada di [`RaceTest`](tests/Feature/RaceTest.php): sepuluh proses PHP terpisah menembak pada detik
yang sama, di Postgres sungguhan.

| Skenario | Dengan kunci | Kunci dicabut |
|---|---|---|
| 10 kartu diseret ke kolom berbatas 3 yang tinggal 1 slot | 1 masuk, 9 ditolak "kolom penuh" | 10 masuk, kolom jadi 12/3 |
| 10 kartu dibuat bersamaan | nomor 1 sampai 10, semua berhasil | 1 berhasil, 9 gagal karena nomor kartu bentrok |

### 2. "Lama" dibelah jadi "lama di mana dan kenapa"

- **Jenis kolom punya arti.** _Antre_ belum dihitung, _Dikerjakan_ mulai menghitung cycle time,
  _Menunggu_ (mis. "Menunggu merge") tetap dihitung tapi sebagai waktu tunggu, _Selesai_ berhenti.
- **Revisi tercatat dengan alasannya.** Kartu yang diseret mundur ke kolom kerja (mis. QA → Dikerjakan)
  ditandai sebagai revisi, dan papan menanyakan alasannya: bug dari QA, perbaikan dari review, atau
  kebutuhan berubah. Arah perpindahan disimpan saat itu juga, karena urutan kolom bisa berubah nanti.
- **[`FlowMetrics::timeBreakdown()`](app/Services/FlowMetrics.php)** membagi cycle time kartu yang selesai
  per kolom, memisahkan waktu kerja dari waktu menunggu dan terblokir, lalu menghitung hari tambahan akibat
  revisi beserta alasan dan kolom asalnya. Hasilnya kalimat seperti: _"QA memakan 20% cycle time; 20% kartu
  dikembalikan, revisi menambah median 2,9 hari; paling sering karena perbaikan dari review."_
- Setiap kartu punya **perjalanan**: bar waktu per kolom, putaran revisi, siapa yang mengembalikan, dan kenapa.

### 3. Perkiraan berupa rentang, bukan satu tanggal

[`FlowMetrics::forecast()`](app/Services/FlowMetrics.php) memutar 5.000 simulasi Monte Carlo dari throughput
harian tim 6 minggu terakhir: _"50% kemungkinan selesai dalam 42 hari, 85% dalam 56 hari."_ Benihnya tetap
per proyek per hari, jadi angkanya tidak berubah-ubah setiap halaman dimuat ulang. Epic dengan tanggal target
dibandingkan dengan perkiraan ini dan diberi status _sesuai target_, _berisiko_, atau _kemungkinan lewat target_.

Metrik lain: cycle time P50/P85 (nearest-rank), throughput mingguan, cumulative flow diagram yang dibangun
ulang dari buku besar perpindahan, dan umur kartu yang sedang dikerjakan dibandingkan dengan riwayat.
Semua grafik digambar sebagai SVG di server; tidak ada pustaka grafik di browser.

## Fitur

- Proyek dengan tiga peran: **pemilik** (kolom, batas WIP, label, anggota, undangan), **anggota**
  (kartu dan epic), **pengamat** (hanya melihat, cocok untuk klien).
- Papan seret-lepas ([SortableJS](https://sortablejs.github.io/Sortable/)). Kolom penuh menolak kartu sejak
  masih diseret, dan server memeriksa ulang di dalam kunci. Ada pengganti seret untuk keyboard di halaman kartu.
- Papan diperbarui sendiri saat orang lain mengubahnya. Vercel tidak bisa menahan WebSocket, jadi browser
  menanyakan satu angka versi tiap 5 detik dan baru mengambil ulang papan kalau angkanya berubah.
- Kunci optimistis di form kartu: kalau dua orang mengedit kartu yang sama, simpanan kedua ditolak dengan
  nama orang yang menyimpan duluan, dan isian form-nya dikembalikan, tidak hilang.
- Epic, label, prioritas, tenggat, checklist, komentar, kartu terblokir beserta alasannya, arsip, dan
  riwayat aktivitas.
- Undangan lewat tautan yang berlaku 7 hari dan bisa dicabut.

## Teknologi

- **Laravel 12** (PHP 8.2+), Blade, Alpine.js, Tailwind CSS v4 (CLI, tanpa Vite).
- **Postgres** di [Neon](https://neon.com). Aturan penting juga dijaga di database: CHECK constraint untuk
  jenis kolom, batas WIP, dan prioritas; unique untuk nomor kartu per proyek.
- **Vercel** lewat runtime komunitas [`vercel-php`](https://github.com/vercel-community/php). Seluruh aplikasi
  jalan sebagai satu fungsi ([`api/index.php`](api/index.php)) di region `sin1`.

## Menjalankan di lokal

Butuh PHP 8.2+ dengan `pdo_pgsql`, Composer, Node (hanya untuk build CSS), dan Postgres.

```bash
composer install
npm install && npm run build      # menghasilkan public/css/app.css
cp .env.example .env              # isi DB_* dengan database Postgres-mu
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Seeder tidak menempelkan kartu begitu saja. [`TeamSimulation`](database/seeders/TeamSimulation.php) memutar
kerja sebuah tim selama 10 minggu, hari demi hari, lewat kelas `Board` yang sama dengan aplikasinya: batas
WIP menahan kartu, satu penguji QA hanya sanggup dua kartu per hari, sebagian kartu dikembalikan karena bug,
dan merge hanya dilakukan Selasa dan Kamis. Karena itu grafik dan perkiraannya punya riwayat yang konsisten.
Riwayatnya relatif terhadap hari seeder dijalankan.

Akun demo (password semuanya `password123`), atau klik **Coba papan demo** di halaman depan:

| Peran di proyek klinik | Email |
|---|---|
| Pemilik | `dewi@alur.test` |
| Anggota | `raka@alur.test` |
| Penguji QA (anggota) | `nadia@alur.test` |
| Pengamat | `laras@alur.test` |

## Tes

```bash
php artisan test
```

Tes memakai Postgres, bukan SQLite, karena penguncian baris dan CHECK constraint termasuk yang diuji.
`phpunit.xml` mengarah ke database `alur_test`. CI menjalankan tes yang sama dengan service Postgres
([`.github/workflows/test.yml`](.github/workflows/test.yml)).

## Deploy ke Vercel

1. Import repo di Vercel. `vercel.json` sudah mengatur runtime, rute, dan env yang tidak rahasia.
2. Isi env rahasia: `APP_KEY`, `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
3. Jalankan migrasi dari lokal ke database produksi: `php artisan migrate --seed`.

CSS di-build di lokal dan ikut di-commit (`public/css/app.css`), jadi Vercel tidak perlu menjalankan Node.

## Berkas yang perlu dibaca dulu

| Berkas | Isi |
|---|---|
| [`app/Services/Board.php`](app/Services/Board.php) | Semua aturan papan: kunci, batas WIP, urutan kartu, revisi |
| [`app/Services/FlowMetrics.php`](app/Services/FlowMetrics.php) | Cycle time, pembagian waktu, CFD, Monte Carlo, progres epic |
| [`tests/Feature/RaceTest.php`](tests/Feature/RaceTest.php) | Sepuluh proses serentak, di database sungguhan |
| [`tests/Feature/ReworkTest.php`](tests/Feature/ReworkTest.php) | Skenario revisi dan pembagian waktu yang bisa dihitung tangan |
| [`resources/css/app.css`](resources/css/app.css) | Sistem desain beserta alasan warna dan angka kontrasnya |
