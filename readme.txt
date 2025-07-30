=== Search Protection ===
Plugin Name: Search Protection
Contributors: hilfans0, telkomuniversity, hilfans
Tags: search, security, block, spam, protection
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.2.0
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

= 1.2.0 =
* PERBAIKAN KEAMANAN: Menambahkan verifikasi nonce pada form pencarian untuk melindungi dari serangan CSRF. Ini adalah perbaikan keamanan penting.
* PENINGKATAN KODE: Melakukan refaktorisasi besar pada query database dan implementasi caching untuk memenuhi standar ketat dari repositori WordPress.org.
* PENINGKATAN PERFORMA: Menambahkan nomor versi pada skrip yang dimuat untuk memastikan pembaruan cache yang benar di browser pengguna.


= 1.1.1 =
* Rilis awal plugin.
* Fitur pemblokiran kata kunci dan ekspresi reguler (regex).
* Integrasi Google reCAPTCHA v3.
* Halaman pengaturan yang lengkap dan mudah digunakan.
* Sistem logging dan pembersihan log otomatis via Cron Job WordPress.
