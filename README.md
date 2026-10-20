# DR.SPORT · Operations Console

Aplikasi internal pengelolaan pesanan jersey dan inventory bahan baku dengan PHP native, PDO, dan MySQL. Antarmuka berbahasa Indonesia, responsif, dan tidak memakai framework frontend eksternal.

## Fitur

- Login, logout, pembatasan percobaan login, CSRF, sesi aman, dan role Super Admin / Admin / Karyawan.
- Dashboard berbasis data, pengelolaan akun, pesanan dan riwayat status.
- Master bahan kain, tinta printing, benang; foto bahan lokal dengan placeholder kategori.
- Stok awal, stok masuk/keluar atomik, locking saldo, pencegahan saldo negatif, serta riwayat ledger.
- Pengecekan ketersediaan Karyawan yang hanya membaca saldo.
- Karyawan dapat mencatat pemakaian bahan; saldo berkurang secara atomik dan riwayat pemakaian akun tercatat.
- Jumlah bahan ditampilkan tanpa nol desimal yang tidak perlu, menggunakan titik sebagai pemisah desimal (misalnya `1000` atau `1.5`).
- Laporan periode pesanan dan inventory, cetak browser, serta audit log.
- Upload JPG/JPEG, PNG, WEBP maksimal 2 MB dengan pemeriksaan MIME, gambar, dan dimensi.

## Menjalankan di XAMPP

1. Pastikan PHP 8 dan MySQL/MariaDB aktif melalui XAMPP. Folder ini dapat diletakkan di `C:\xampp\htdocs\Sport`.
2. Buat konfigurasi lokal dengan menyalin `config\database.example.php` ke `config\database.local.php`, lalu sesuaikan kredensial MySQL. File konfigurasi lokal diabaikan Git.
3. Import `database\drsport_schema.sql` menggunakan phpMyAdmin atau jalankan:

   ```powershell
   cmd.exe /c "C:\xampp\mysql\bin\mysql.exe -u root < database\drsport_schema.sql"
   ```

   Tambahkan `-p` jika user database memiliki password.
4. Buat akun Super Admin pertama melalui terminal:

   ```powershell
   C:\xampp\php\php.exe setup_admin.php
   ```

   Setup ini hanya menerima satu akun Super Admin pertama dan hanya berjalan melalui CLI. Username 3–40 karakter memakai huruf, angka, titik, atau garis bawah; password 10–72 karakter dengan huruf dan angka. Jangan gunakan akun database berhak root untuk deployment.
5. Buka `http://localhost/Sport/`. Buat Admin/Karyawan melalui menu **Pengguna**.
6. Setelah pembuatan Super Admin, pindahkan atau hapus `setup_admin.php` dari direktori web.

## Persyaratan

- PHP 8.0+ dengan ekstensi PDO MySQL, Fileinfo, dan GD/getimagesize tersedia.
- MySQL 5.7+/MariaDB yang mendukung InnoDB, transaksi, foreign key, dan `SELECT ... FOR UPDATE`.
- Apache 2.4 dengan `mod_authz_core`; folder upload dilindungi `.htaccess`.

## Keamanan & konfigurasi

- Jangan publikasikan `config/database.local.php`, kredensial, atau file upload pengguna.
- Gunakan HTTPS saat aplikasi diakses melalui jaringan dan akun database dengan hak minimum.
- Folder `uploads/materials/` menonaktifkan listing dan menolak eksekusi file skrip pada Apache XAMPP.
- Password disimpan dengan `password_hash`; semua mutasi mengharuskan token CSRF.
- Transaksi inventory memakai row locking dan menyimpan saldo serta ledger dalam transaksi database yang sama.

## Catatan implementasi

- Status pengerjaan mengikuti tahapan sementara di PRD dan perlu disesuaikan dengan alur nyata workshop.
- Satuan serta ambang minimum stok ditentukan Admin berdasarkan operasional perusahaan.
- Pembaruan stok lintas pengguna terlihat setelah refresh halaman; polling otomatis/WebSocket belum diaktifkan.
- Daftar bahan dan pesanan menggunakan pagination 20 baris; log dan mutasi terbaru dibatasi 300 baris per tampilan.
- Saldo akhir pada laporan inventory adalah saldo terkini; mutasi masuk/keluar dan saldo awal periode diturunkan dari ledger.
