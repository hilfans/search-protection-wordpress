Search Protection for WordPress
Plugin WordPress yang sederhana namun kuat untuk melindungi form pencarian Anda dari kata kunci yang tidak diinginkan, spam, dan bot jahat menggunakan daftar hitam (blacklist) dan integrasi Google reCAPTCHA v3.

Deskripsi
Form pencarian sering menjadi target serangan spam dan upaya pencarian berbahaya. Plugin Search Protection menyediakan dua lapis pertahanan:

Daftar Hitam (Blacklist): Memblokir pencarian yang mengandung kata-kata spesifik atau pola karakter (regex) yang Anda tentukan.

Google reCAPTCHA v3: Secara transparan memverifikasi apakah pengunjung adalah manusia atau bot, dan memblokir permintaan yang mencurigakan tanpa mengganggu pengguna asli.

Plugin ini dirancang agar mudah dikonfigurasi dan bekerja secara otomatis di latar belakang untuk menjaga situs Anda tetap aman.

Fitur Utama
Pemblokiran Kata Kunci: Blokir pencarian berdasarkan daftar kata-kata yang tidak diinginkan (misalnya: spam, judi).

Pemblokiran Regex: Gunakan Ekspresi Reguler (Regular Expressions) untuk memblokir pola pencarian yang lebih kompleks (misalnya, karakter non-ASCII, URL, dll).

Integrasi Google reCAPTCHA v3: Menambahkan lapisan keamanan tak terlihat untuk mendeteksi dan memblokir lalu lintas bot.

Pesan Blokir Kustom: Atur pesan yang akan ditampilkan kepada pengguna saat pencarian mereka diblokir.

Pengalihan Halaman Blokir: Alihkan pengguna ke halaman tertentu saat pencarian mereka diblokir.

Logging (Pencatatan): Mencatat semua upaya pencarian yang diblokir untuk dianalisis lebih lanjut. Log dibersihkan secara otomatis setiap 24 jam.

Antarmuka Pengaturan yang Mudah: Semua opsi terintegrasi dengan baik di dalam dasbor WordPress.

Instalasi
Unduh file .zip dari repositori ini.

Buka Dasbor WordPress Anda, navigasi ke Plugins > Add New.

Klik Upload Plugin dan pilih file .zip yang baru saja Anda unduh.

Aktifkan plugin setelah instalasi selesai.

Selesai!

Konfigurasi
Setelah aktivasi, buka Pengaturan > Search Protection di dasbor WordPress Anda.

Pengaturan reCAPTCHA v3:

Centang Aktifkan reCAPTCHA untuk menggunakannya.

Daftarkan domain Anda di Google reCAPTCHA Admin untuk mendapatkan Site Key dan Secret Key.

Masukkan kedua kunci tersebut ke kolom yang sesuai.

Pengaturan Pemblokiran Kata:

Di kolom Daftar Kata/Pola Terlarang, masukkan kata atau pola yang ingin Anda blokir, pisahkan dengan koma.

Untuk kata biasa: spam, judi, test

Untuk regex: Apit pola dengan garis miring, contoh: /[^\x20-\x7E]/ untuk memblokir semua karakter non-ASCII.

Pengaturan Pesan & Pengalihan:

Sesuaikan pesan yang akan ditampilkan untuk setiap jenis pemblokiran.

Jika Anda ingin mengalihkan pengguna ke halaman lain saat diblokir, masukkan URL lengkap di kolom URL Halaman Blokir Kustom.

Klik Save Changes.

Changelog
1.1.1 (20 Juli 2025)
Rilis awal plugin.

Fitur pemblokiran kata kunci dan regex.

Integrasi Google reCAPTCHA v3.

Halaman pengaturan yang lengkap.

Sistem logging dan pembersihan log otomatis via Cron Job.

Lisensi
Plugin ini dirilis di bawah lisensi GPLv2. Lihat file LICENSE untuk detailnya. Dan dengan dukungan Telkom University

## Changelog

### 1.2.0 (22 Juli 2025)
* **PERBAIKAN KEAMANAN:** Menambahkan verifikasi nonce pada form pencarian untuk melindungi dari serangan CSRF. Ini adalah perbaikan keamanan penting.
* **PENINGKATAN KODE:** Melakukan refaktorisasi besar pada query database dan implementasi caching untuk memenuhi standar ketat dari repositori WordPress.org.
* **PENINGKATAN PERFORMA:** Menambahkan nomor versi pada skrip yang dimuat untuk memastikan pembaruan cache yang benar di browser pengguna.


### 1.1.1 (20 Juli 2025)
* Rilis awal plugin.
* Fitur pemblokiran kata kunci dan regex.
* Integrasi Google reCAPTCHA v3.
* Halaman pengaturan yang lengkap.
* Sistem logging dan pembersihan log otomatis via Cron Job.
