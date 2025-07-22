=== Search Protection ===
Plugin Name: Search Protection
Contributors: hilfans0, telkomuniversity, hilfans
Tags: search, security, recaptcha, block, spam, protection, blacklist, firewall
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.1.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lindungi form pencarian Anda dari spam dan karakter berbahaya dengan daftar hitam dan integrasi Google reCAPTCHA v3.

== Description ==

Plugin **Search Protection** menyediakan dua lapis pertahanan untuk form pencarian standar WordPress Anda untuk melindunginya dari serangan spam dan upaya pencarian berbahaya.

* **Daftar Hitam (Blacklist)**: Blokir pencarian yang mengandung kata-kata spesifik atau pola karakter (regex) yang Anda tentukan.
* **Google reCAPTCHA v3**: Verifikasi secara transparan apakah pengunjung adalah manusia atau bot, dan blokir permintaan yang mencurigakan tanpa mengganggu pengguna asli.

Plugin ini dirancang agar mudah dikonfigurasi dan bekerja secara otomatis di latar belakang untuk menjaga situs Anda tetap aman.

== Installation ==

1.  Unggah folder `search-protection` ke direktori `/wp-content/plugins/`.
2.  Aktifkan plugin melalui menu 'Plugins' di WordPress.
3.  Buka **Pengaturan > Search Protection** untuk melakukan konfigurasi.

== Frequently Asked Questions ==

= Apakah saya perlu akun Google reCAPTCHA? =

Ya, jika Anda ingin mengaktifkan fitur reCAPTCHA v3. Anda bisa mendapatkannya secara gratis dari [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin).

= Apakah plugin ini memperlambat situs saya? =

Tidak. Plugin ini sangat ringan. Proses pemblokiran terjadi di sisi server sebelum WordPress menjalankan kueri pencarian yang berat. Pembersihan log juga dijadwalkan dengan Cron Job agar tidak membebani server.

== Screenshots ==

1.  Halaman pengaturan utama untuk konfigurasi reCAPTCHA, daftar hitam, dan pesan kustom.

== Changelog ==

= 1.2.3 =
* PERBAIKAN: Mengatasi error fatal PHP (`TypeError: array_merge()`) yang terjadi saat plugin diaktifkan pertama kali sebelum pengaturan disimpan. Menggunakan `wp_parse_args()` untuk penanganan default yang lebih aman.

= 1.2.2 =
* PERBAIKAN: Mengatasi bug kritis di mana skrip reCAPTCHA bisa menghentikan fungsi formulir pencarian default. Formulir sekarang akan selalu berfungsi, dan validasi token diserahkan ke backend jika skrip gagal.

= 1.2.1 =
* PERBAIKAN: Mengubah tautan "Back" pada halaman blokir agar selalu mengarah ke beranda situs.

= 1.2.0 =
* FITUR: Menambahkan panel informasi di halaman pengaturan untuk menampilkan kata kunci yang terblokir dalam 24 jam terakhir.
* PENINGKATAN: Memudahkan admin menyalin kata kunci yang sering diblokir untuk dimasukkan ke daftar hitam.

= 1.1.1 =
* Rilis awal plugin.
