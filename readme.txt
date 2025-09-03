=== Search Protection ===
Plugin Name: Search Protection
Contributors: hilfans0, telkomuniversity
Tags: search, security, block, spam, protection
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.5.9
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://endowment.telkomuniversity.ac.id/donasi-langsung/

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

== Layanan Eksternal ==

Plugin ini terintegrasi dengan layanan Google reCAPTCHA v3 untuk melindungi form pencarian dari spam dan bot berbahaya. Fitur ini bersifat opsional dan dapat diaktifkan atau dinonaktifkan dari halaman pengaturan plugin.

* **Layanan:** Google reCAPTCHA v3
* **Data yang Dikirim:** Saat pengguna mengirimkan form pencarian dan fitur reCAPTCHA diaktifkan, alamat IP pengguna dan token reCAPTCHA akan dikirim ke server Google untuk diverifikasi.
* **Syarat dan Kebijakan Layanan:** Untuk informasi lebih lanjut, silakan tinjau [Persyaratan Layanan](https://policies.google.com/terms) dan [Kebijakan Privasi](https://policies.google.com/privacy) dari Google.

== Screenshots ==

1.  Halaman pengaturan utama untuk Pengaturan reCAPTCHA v3, Pengaturan Pemblokiran Kata.
2.  Menu pengaturan utama untuk Pengaturan Pesan & Pengalihan, Manajemen Data, Simpan Semua Perubahan.
3.  Menu pengaturan utama untuk Cadangkan & Pulihkan Pengaturan. 

== Changelog ==

= 1.5.9 (3 September 2025) =
" PENINGKATAN KEAMANAN: Memperketat validasi pada file yang diunggah dengan menambahkan pengecekan isset() pada $_FILES['import_file']['error'] untuk menghilangkan peringatan InputNotValidated dari alat pemeriksa plugin.  Menambah pengecekan format file json yang diupload agar sesuai template pada saat backup.

= 1.5.0 (26 Agustus 2025) =
* PENINGKATAN KEAMANAN: Semua input sekarang disanitasi lebih awal dan semua output di-escape dengan fungsi esc_*() sesuai konteks.
* PENINGKATAN KEAMANAN: Penambahan verifikasi nonce pada form impor/ekspor pengaturan serta pada token reCAPTCHA di form pencarian.
* PENINGKATAN KEAMANAN: Validasi unggahan file cadangan .json ditingkatkan dengan pengecekan tipe file, ukuran maksimal, dan penggunaan WP_Filesystem.
* PENINGKATAN KODE: Query database dibungkus dengan $wpdb->prepare() dan nama tabel diamankan dengan esc_sql(). Ditambahkan anotasi phpcs:ignore dengan justifikasi untuk menghindari false positive.
* PERBAIKAN: Menghapus kode debug set_error_handler() dan fungsi non‑produksi lain yang ditandai oleh pemeriksa kode.
* PERBAIKAN: Semua admin notice di-escape dengan aman dan markup diperbaiki.
* PERBAIKAN: uninstall.php diperkuat untuk menghapus cron job, opsi plugin, dan tabel log sesuai dengan opsi delete_on_uninstall.

= 1.4.1 (22 Agustus 2025) =
* PENINGKATAN KEPATUHAN: Mengubah semua prefix internal plugin (misalnya `sph_`) menjadi `search_protect_` untuk memenuhi persyaratan keunikan dan panjang minimal dari WordPress.org.
* PENINGKATAN KEAMANAN: Mengganti fungsi `echo json_encode` dengan `wp_send_json` untuk proses ekspor pengaturan yang lebih aman dan sesuai standar WordPress.
* PENINGKATAN KEAMANAN: Menambahkan sanitasi eksplisit pada nama file yang diunggah saat proses impor pengaturan.
* DOKUMENTASI: Menambahkan bagian "Layanan Eksternal" pada file readme untuk menjelaskan penggunaan API Google reCAPTCHA sesuai pedoman.
* CATATAN PENTING: Karena perubahan prefix internal yang signifikan, semua pengaturan plugin akan direset setelah melakukan update ke versi ini. Harap lakukan konfigurasi ulang atau pulihkan dari cadangan.

= 1.3.2 (31 Juli 2025) =
* PENINGKATAN KEAMANAN: Memperketat validasi, sanitasi, dan escaping pada semua input dan output untuk lolos dari semua pemeriksaan keamanan otomatis oleh tim WordPress.org.
* PENINGKATAN KODE: Memperbaiki cara pemanggilan skrip reCAPTCHA agar sepenuhnya sesuai standar WordPress menggunakan `wp_enqueue_script`, meningkatkan keamanan dan kompatibilitas tema.
* PENINGKATAN PERFORMA: Menambahkan nomor versi pada aset skrip untuk memastikan pengguna selalu mendapatkan versi terbaru setelah pembaruan (cache-busting).
* PENINGKATAN PERFORMA: Mengimplementasikan object caching (`wp_cache_get`) untuk query database di halaman pengaturan untuk mengurangi beban server pada situs dengan lalu lintas tinggi.
* PERBAIKAN: Menambahkan komentar `phpcs:ignore` yang diperlukan untuk menangani temuan *false positive* dari pemindai kode otomatis, memastikan plugin lolos semua pemeriksaan standar WordPress.

= 1.2.0 =
* FITUR: Menambahkan panel informasi di halaman pengaturan untuk menampilkan kata kunci yang terblokir dalam 24 jam terakhir.
* PENINGKATAN: Memudahkan admin menyalin kata kunci yang sering diblokir untuk dimasukkan ke daftar hitam.

= 1.1.1 =
* Rilis awal plugin.

== Upgrade Notice ==
= 1.5.9 =
Versi ini berisi perbaikan keamanan penting dan penyempurnaan kode untuk memenuhi standar WordPress.org. Pembaruan sangat direkomendasikan.
