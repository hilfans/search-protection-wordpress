=== Search Protection ===
Plugin Name: Search Protection
Contributors: hilfans0
Tags: search, security, block, spam, protection
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.4.0
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

Tidak. Plugin ini sangat ringan. Proses pemblokiran terjadi di sisi server sebelum WordPress menjalankan kueri pencarian yang berat ke database. Pembersihan log juga dijadwalkan dengan Cron Job agar tidak membebani server.

== Screenshots ==

1.  Halaman pengaturan utama untuk Pengaturan reCAPTCHA v3, Pengaturan Pemblokiran Kata.
2.  Menu pengaturan utama untuk Pengaturan Pesan & Pengalihan, Manajemen Data, Simpan Semua Perubahan.
3.  Menu pengaturan utama untuk Cadangkan & Pulihkan Pengaturan. 

== Changelog ==

= 1.4.1 (22 Agustus 2025) =
* PENINGKATAN KEPATUHAN: Mengubah semua prefix internal plugin (misalnya `sph_`) menjadi `search_protect_` untuk memenuhi persyaratan keunikan dan panjang minimal dari WordPress.org.
* PENINGKATAN KEAMANAN: Mengganti fungsi `echo json_encode` dengan `wp_send_json` untuk proses ekspor pengaturan yang lebih aman dan sesuai standar WordPress.
* PENINGKATAN KEAMANAN: Menambahkan sanitasi eksplisit pada nama file yang diunggah saat proses impor pengaturan.
* DOKUMENTASI: Menambahkan bagian "Layanan Eksternal" pada file readme untuk menjelaskan penggunaan API Google reCAPTCHA sesuai pedoman.
* CATATAN PENTING: Karena perubahan prefix internal yang signifikan, semua pengaturan plugin akan direset setelah melakukan update ke versi ini. Harap lakukan konfigurasi ulang atau pulihkan dari cadangan.

= 1.4.0 (12 Agustus 2025) =
* FITUR: Menambahkan opsi di halaman pengaturan untuk mengaktifkan atau menonaktifkan penghapusan log otomatis setiap 24 jam.
* PENINGKATAN: Menghapus semua nama dan prefix yang berasosiasi dengan institusi (`TelU`, `telu_`) dan menggantinya dengan prefix unik (`SPH`, `sph_`) untuk mematuhi pedoman WordPress.org.
* CATATAN PENTING: Karena perubahan nama internal, semua pengaturan plugin akan direset setelah melakukan update ke versi ini. Harap konfigurasikan ulang setelah pembaruan.

= 1.3.2 (31 Juli 2025) =
* PENINGKATAN KEAMANAN: Memperketat validasi, sanitasi, dan escaping pada semua input dan output untuk lolos dari semua pemeriksaan keamanan otomatis oleh tim WordPress.org.
* PENINGKATAN KODE: Memperbaiki cara pemanggilan skrip reCAPTCHA agar sepenuhnya sesuai standar WordPress menggunakan `wp_enqueue_script`, meningkatkan keamanan dan kompatibilitas tema.
* PENINGKATAN PERFORMA: Menambahkan nomor versi pada aset skrip untuk memastikan pengguna selalu mendapatkan versi terbaru setelah pembaruan (cache-busting).
* PENINGKATAN PERFORMA: Mengimplementasikan object caching (`wp_cache_get`) untuk query database di halaman pengaturan untuk mengurangi beban server pada situs dengan lalu lintas tinggi.
* PERBAIKAN: Menambahkan komentar `phpcs:ignore` yang diperlukan untuk menangani temuan *false positive* dari pemindai kode otomatis, memastikan plugin lolos semua pemeriksaan standar WordPress.

= 1.3.1 (28 Juli 2025) =
* PERBAIKAN: Memperbaiki bug kritis di mana semua pengaturan plugin bisa terhapus setelah 24 jam. Masalah ini disebabkan oleh konflik nama pada tugas terjadwal (cron job) yang digunakan untuk membersihkan log. Nama cron job telah diubah menjadi lebih spesifik untuk mencegah konflik dengan plugin lain dan memastikan hanya data log yang dihapus.
* PENINGKATAN: Proses aktivasi plugin kini secara otomatis menghapus jadwal cron lama (jika ada) untuk memastikan transisi yang mulus saat memperbarui plugin.

= 1.3.0 =
* FITUR: Menambahkan opsi untuk menghapus semua data plugin (pengaturan dan log) saat plugin di-uninstall.
* FITUR: Menambahkan fungsionalitas untuk mencadangkan (ekspor) dan memulihkan (impor) semua pengaturan plugin.
* PERBAIKAN: Mengatasi bug kritis di mana skrip reCAPTCHA tidak berfungsi pada tampilan mobile atau pada form yang dimuat secara dinamis.
* PERBAIKAN: Mengatasi error fatal PHP yang terjadi saat plugin diaktifkan pertama kali.
* PERBAIKAN: Mengubah tautan "Back" pada halaman blokir agar selalu mengarah ke beranda situs.
* PENINGKATAN: Mengembalikan teks informasi tentang penghapusan log otomatis di halaman pengaturan.

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
