# Search Protection

**Search Protection** adalah plugin WordPress yang menyediakan dua lapis pertahanan untuk form pencarian standar WordPress agar tetap aman dari serangan spam dan upaya pencarian berbahaya.

## ✨ Fitur

- **Daftar Hitam (Blacklist)**  
  Blokir pencarian yang mengandung kata-kata spesifik atau pola karakter (regex) yang Anda tentukan.

- **Google reCAPTCHA v3**  
  Verifikasi secara transparan apakah pengunjung adalah manusia atau bot, dan blokir permintaan yang mencurigakan tanpa mengganggu pengguna asli.

Plugin ini dirancang agar mudah dikonfigurasi dan bekerja otomatis di latar belakang untuk menjaga situs Anda tetap aman.

---

## 🚀 Instalasi

1. Unggah folder `search-protection` ke direktori `/wp-content/plugins/`.
2. Aktifkan plugin melalui menu **Plugins** di WordPress.
3. Buka **Pengaturan > Search Protection** untuk melakukan konfigurasi.

---

## ❓ FAQ

### Apakah saya perlu akun Google reCAPTCHA?
Ya, jika Anda ingin mengaktifkan fitur reCAPTCHA v3. Anda bisa mendapatkannya secara gratis dari [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin).

### Apakah plugin ini memperlambat situs saya?
Tidak. Plugin ini sangat ringan. Proses pemblokiran terjadi di sisi server sebelum WordPress menjalankan kueri pencarian yang berat ke database.  
Pembersihan log juga dijadwalkan dengan **Cron Job** agar tidak membebani server.

---

## 🔒 Layanan Eksternal

Plugin ini terintegrasi dengan layanan **Google reCAPTCHA v3** untuk melindungi form pencarian dari spam dan bot berbahaya.  
Fitur ini bersifat opsional dan dapat diaktifkan/dinonaktifkan dari halaman pengaturan plugin.

- **Layanan:** Google reCAPTCHA v3  
- **Data yang Dikirim:** Saat pengguna mengirimkan form pencarian (dengan reCAPTCHA aktif), alamat IP pengguna dan tok
