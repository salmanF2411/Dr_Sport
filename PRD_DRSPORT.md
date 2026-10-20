# PRODUCT REQUIREMENTS DOCUMENT (PRD)
# Sistem Informasi Pengelolaan Pesanan Jersey Printing Berbasis Inventory Real-Time — DR.SPORT

**Versi dokumen:** 1.1  
**Status:** Rancangan kebutuhan untuk implementasi

**Perubahan versi 1.1:** Penyederhanaan UI/UX, penambahan thumbnail/foto bahan baku, dan spesifikasi keamanan unggah gambar.  
**Jenis aplikasi:** Internal web application  
**Bahasa antarmuka:** Bahasa Indonesia  
**Stack:** PHP 8 Native, MySQL, HTML5, CSS3, Bootstrap 4, AdminLTE 3, JavaScript, jQuery, Font Awesome, XAMPP, Visual Studio Code

---

## 1. Ringkasan Produk

Sistem Informasi DR.SPORT adalah aplikasi internal berbasis web untuk membantu Konveksi DR.SPORT mengelola pesanan jersey printing dan ketersediaan bahan baku secara terpusat.

Sistem berfokus pada dua proses yang saling terkait:

1. **Pengelolaan pesanan jersey printing** — Admin mencatat pesanan dan memperbarui status pengerjaannya.
2. **Pengelolaan inventory real-time** — Admin mencatat stok masuk, stok keluar, dan pemakaian bahan yang terkait dengan pesanan. Perubahan stok tersimpan sebagai transaksi dan memperbarui saldo stok sistem.

Bahan baku yang masuk ruang lingkup utama adalah **kain, tinta printing, dan benang**. Karyawan menggunakan sistem untuk memeriksa ketersediaan bahan sebelum menggunakannya. Super Admin mengelola akun Admin dan Karyawan serta memantau keseluruhan aktivitas sistem.

Sistem ini merupakan aplikasi **internal**, bukan website e-commerce publik. Pelanggan tidak membuat akun atau melakukan checkout sendiri pada versi pertama; pesanan dicatat oleh Admin.

## 2. Latar Belakang dan Masalah

Proposal kerja praktik DR.SPORT menjelaskan bahwa pencatatan pesanan, pemantauan status pengerjaan, dan pengelolaan stok bahan baku masih memiliki proses manual atau semi-manual. Dampaknya meliputi:

- data pesanan sulit dipantau secara konsisten;
- status pengerjaan sulit diketahui dengan cepat;
- stok bahan baku berisiko tidak sesuai dengan kondisi aktual;
- kekurangan bahan dapat baru diketahui ketika bahan hendak digunakan;
- proses pencatatan dan monitoring menjadi kurang efisien.

Sistem ini ditujukan untuk mengintegrasikan data pesanan dengan inventory, memudahkan pengecekan ketersediaan, dan menyediakan informasi operasional yang lebih konsisten.

## 3. Tujuan Sistem

1. Mengelola pencatatan pesanan jersey printing secara terstruktur.
2. Mempermudah Admin memperbarui status pesanan.
3. Menampilkan informasi stok kain, tinta printing, dan benang dengan cepat.
4. Mencatat setiap penambahan, pengurangan, dan penyesuaian stok.
5. Memungkinkan Karyawan mengecek ketersediaan bahan sebelum digunakan.
6. Mempermudah Super Admin mengelola akun Admin dan Karyawan.
7. Menyediakan ringkasan dan laporan pesanan serta inventory.
8. Mengurangi kesalahan input dan selisih antara stok sistem dengan stok fisik.
9. Menyimpan riwayat aktivitas penting untuk audit.

## 4. Ruang Lingkup

### 4.1 Fitur yang termasuk

- Login dan logout.
- Hak akses berdasarkan role: Super Admin, Admin, dan Karyawan.
- Dashboard sesuai role.
- Pengelolaan akun Admin dan Karyawan oleh Super Admin.
- Pengelolaan data pesanan jersey.
- Pemantauan dan pembaruan status pesanan.
- Master inventory bahan baku: kain, tinta printing, dan benang.
- Foto/thumbnail untuk setiap bahan, menggunakan foto yang diunggah atau placeholder sesuai kategori.
- Pencatatan stok masuk.
- Pencatatan pemakaian/stok keluar, termasuk keterkaitannya dengan pesanan jika relevan.
- Pengecekan ketersediaan stok oleh Karyawan.
- Riwayat pergerakan stok.
- Peringatan stok menipis berdasarkan ambang minimum yang dikonfigurasi.
- Laporan pesanan dan inventory.
- Log aktivitas pengguna.
- Validasi data dan kontrol keamanan.

### 4.2 Di luar ruang lingkup

Sesuai batasan proposal, sistem tidak mencakup:

- pembayaran online;
- pengelolaan atau tracking pengiriman;
- manajemen keuangan perusahaan;
- produksi otomatis;
- aplikasi mobile native;
- portal pelanggan/e-commerce publik;
- perhitungan otomatis kebutuhan bahan dari pola atau desain jersey.

### 4.3 Penyesuaian role

Proposal awal membatasi pengguna sistem pada Admin. PRD ini menerapkan **tiga role**—Super Admin, Admin, dan Karyawan—berdasarkan kebutuhan terbaru yang ditentukan pemilik proyek. Penyesuaian ini merupakan perluasan hak akses yang perlu disepakati dengan pihak DR.SPORT.

### 4.4 Asumsi operasional yang perlu dikonfirmasi

- Satuan yang dipakai pada stok harus mengikuti cara pencatatan nyata perusahaan. Contoh satuan dapat berupa meter, roll, liter, botol, kilogram, atau gulung; contoh ini bukan penetapan satuan final.
- Admin memasukkan kebutuhan/pemakaian bahan berdasarkan informasi nyata. Sistem tidak menghitung kebutuhan bahan dari desain jersey secara otomatis.
- Saat bahan benar-benar dikeluarkan untuk pekerjaan, Admin mencatat pengeluaran/pemakaian tersebut. Karyawan pada versi inti dapat memeriksa ketersediaan dan tidak bisa mengubah saldo stok secara langsung.
- Bila kelak Karyawan perlu mengajukan permintaan bahan dari sistem, fitur permintaan dan persetujuan dapat ditambahkan sebagai pengembangan lanjutan.

## 5. Teknologi Pengembangan

### Backend — PHP 8 Native

Digunakan untuk pemrosesan form, validasi server-side, autentikasi, manajemen sesi, otorisasi role, logika pesanan dan inventory, transaksi database, serta penyusunan laporan. Framework PHP tambahan tidak diperlukan pada versi awal.

### Database — MySQL

Digunakan untuk menyimpan pengguna, pelanggan, pesanan, bahan baku, transaksi inventory, riwayat status, dan aktivitas. Terapkan primary key, foreign key, index, dan charset `utf8mb4`.

### Frontend

- **HTML5:** struktur halaman semantik.
- **CSS3:** desain khusus dan penyesuaian visual.
- **Bootstrap 4:** grid, form, tabel, modal, tombol, dan responsive utilities.
- **AdminLTE 3:** kerangka dashboard administrasi berbasis Bootstrap 4.
- **JavaScript:** interaksi sisi browser.
- **jQuery:** interaksi AJAX dan manipulasi UI.
- **Font Awesome:** ikon antarmuka.

### Tools

- **XAMPP:** Apache dan MySQL untuk pengembangan lokal.
- **Visual Studio Code:** editor kode.

### Konsistensi teknis

- Gunakan satu versi Bootstrap 4 yang sesuai dengan AdminLTE 3.
- Jangan mencampur Bootstrap 5 atau Tailwind pada project ini.
- Gunakan PDO atau MySQLi dengan prepared statements untuk query.
- Simpan konfigurasi rahasia di file konfigurasi lokal yang tidak dimasukkan ke repository publik.

## 6. Jenis Pengguna dan Hak Akses

### 6.1 Super Admin

**Tujuan:** mengelola akses pengguna dan memantau keseluruhan sistem.

Hak akses:
- login/logout;
- melihat dashboard keseluruhan;
- membuat akun Admin;
- membuat akun Karyawan;
- mengedit nama, username/email, dan status akun;
- mengaktifkan atau menonaktifkan akun;
- melakukan reset password melalui alur aman;
- melihat data pesanan;
- memantau inventory dan riwayat stok;
- melihat laporan;
- melihat log aktivitas.

Batasan:
- tidak ada fitur untuk menghapus log audit secara manual;
- akun Super Admin pertama dibuat melalui setup/seed yang aman, bukan registrasi publik;
- sistem harus mencegah penonaktifan akun Super Admin terakhir tanpa prosedur pengganti yang aman.

### 6.2 Admin

**Tujuan:** menjalankan operasional data pesanan dan inventory.

Hak akses:
- login/logout;
- melihat dashboard operasional;
- membuat dan memperbarui pesanan;
- memperbarui status pesanan;
- mengelola data pelanggan yang diperlukan untuk pesanan;
- menambah dan mengubah master bahan baku;
- mencatat stok masuk;
- mencatat pemakaian/stok keluar;
- mengaitkan pengeluaran bahan dengan pesanan;
- melihat ketersediaan dan riwayat stok;
- melihat laporan pesanan dan inventory.

Batasan:
- tidak dapat membuat akun Admin, Karyawan, atau Super Admin;
- tidak dapat mengubah role akun;
- tidak dapat menghapus riwayat transaksi stok;
- koreksi stok wajib dilakukan melalui transaksi penyesuaian dengan alasan.

### 6.3 Karyawan

**Tujuan:** memeriksa status dan ketersediaan bahan yang diperlukan dalam pekerjaan.

Hak akses:
- login/logout;
- melihat dashboard ringkas;
- melihat daftar bahan baku;
- mencari bahan berdasarkan nama, kode, atau kategori;
- melihat stok terkini dan satuannya;
- melihat label ketersediaan: tersedia, terbatas, atau habis;
- mengecek apakah jumlah yang diperlukan tersedia;
- melihat status pesanan yang relevan dengan pekerjaan, sesuai kebijakan akses.

Batasan:
- tidak dapat menambah, mengedit, atau menghapus bahan baku;
- tidak dapat mengubah stok;
- tidak dapat membuat atau mengubah pesanan;
- tidak dapat melihat manajemen akun, laporan sensitif, atau log administrasi;
- data pelanggan hanya ditampilkan bila memang diperlukan untuk pekerjaan.

### 6.4 Matriks izin

| Fitur | Super Admin | Admin | Karyawan |
|---|---|---|---|
| Dashboard sesuai role | Ya | Ya | Ya |
| Membuat akun Admin/Karyawan | Ya | Tidak | Tidak |
| Mengaktifkan/menonaktifkan akun | Ya | Tidak | Tidak |
| Melihat inventory | Ya | Ya | Ya |
| Mengelola master bahan | Pantau | Ya | Tidak |
| Mencatat stok masuk | Pantau | Ya | Tidak |
| Mencatat stok keluar/pemakaian | Pantau | Ya | Tidak |
| Melakukan penyesuaian stok | Pantau | Ya, dengan alasan | Tidak |
| Mengecek ketersediaan bahan | Ya | Ya | Ya |
| Membuat/mengedit pesanan | Pantau | Ya | Tidak |
| Mengubah status pesanan | Pantau | Ya | Tidak |
| Melihat laporan | Ya | Ya | Tidak, kecuali ringkasan sederhana disetujui |
| Melihat log aktivitas | Ya | Terbatas | Tidak |
| Mengubah role | Ya, sesuai aturan | Tidak | Tidak |
| Menghapus log audit | Tidak melalui UI | Tidak | Tidak |

**Semua aturan akses harus ditegakkan di sisi server**, bukan hanya dengan menyembunyikan menu.

## 7. Struktur Menu dan Halaman

### 7.1 Halaman autentikasi

- Login
- Logout
- Ganti password
- Pesan akun tidak aktif atau kredensial tidak valid

Registrasi publik tidak disediakan.

### 7.2 Menu Super Admin

```text
Dashboard
Manajemen Pengguna
  ├── Daftar Akun
  ├── Tambah Admin
  ├── Tambah Karyawan
  └── Detail/Edit Akun
Pesanan
Inventory
  ├── Daftar Bahan
  └── Riwayat Stok
Laporan
Log Aktivitas
Profil / Ganti Password
Logout
```

### 7.3 Menu Admin

```text
Dashboard
Pesanan
  ├── Daftar Pesanan
  ├── Tambah Pesanan
  └── Detail Pesanan
Inventory
  ├── Daftar Bahan
  ├── Tambah/Edit Bahan
  ├── Stok Masuk
  ├── Stok Keluar/Pemakaian
  └── Riwayat Stok
Laporan
Profil / Ganti Password
Logout
```

### 7.4 Menu Karyawan

```text
Dashboard
Cek Ketersediaan Bahan
Daftar Bahan
Status Pesanan (sesuai hak akses)
Profil / Ganti Password
Logout
```

## 8. Functional Requirements

### FR-01 — Login

Sistem memvalidasi username/email, password, dan status akun.

Kriteria:
- akun aktif dengan kredensial valid dapat masuk;
- akun nonaktif tidak dapat masuk;
- pesan kesalahan tidak mengungkap apakah username/email tertentu terdaftar;
- sesi dibuat ulang memakai `session_regenerate_id(true)` setelah login berhasil;
- pengguna diarahkan ke dashboard sesuai role;
- percobaan login berulang dibatasi dengan rate limiting atau jeda.

### FR-02 — Hak Akses Berbasis Role

Setiap halaman dan endpoint memeriksa role dari sesi yang tervalidasi.

Kriteria:
- Karyawan yang membuka URL halaman Admin secara langsung tetap ditolak;
- Admin tidak dapat membuat akun dengan mengirim request langsung;
- role tidak dipercaya jika dikirim dari form, query string, atau JavaScript;
- operasi sensitif yang ditolak dicatat bila relevan.

### FR-03 — Manajemen Akun (Super Admin)

Super Admin dapat membuat akun Admin dan Karyawan, melihat daftar pengguna, memperbarui informasi yang diizinkan, mengaktifkan/menonaktifkan akun, dan memicu reset password.

Validasi:
- username harus unik;
- email unik bila digunakan;
- password awal harus kuat atau dibuat secara acak;
- password disimpan dalam bentuk hash;
- akun yang memiliki riwayat penting tidak dihapus permanen—gunakan status nonaktif;
- akun Super Admin terakhir tidak dapat dinonaktifkan tanpa proses pengganti yang aman.

### FR-04 — Dashboard Super Admin

Menampilkan jumlah akun aktif, jumlah Admin/Karyawan, jumlah pesanan menurut status, ringkasan stok, bahan yang mencapai batas minimum, dan aktivitas sistem terbaru. Angka harus berasal dari database, bukan data statis.

### FR-05 — Dashboard Admin

Menampilkan pesanan baru, pesanan dalam proses, pesanan selesai, ringkasan stok kain/tinta/benang, stok menipis, dan transaksi inventory terbaru.

### FR-06 — Dashboard Karyawan

Menampilkan pencarian bahan, stok terbaru, status ketersediaan, dan status pesanan yang relevan jika diizinkan. Dashboard tidak menyediakan kontrol mutasi stok.

### FR-07 — Master Bahan Baku dan Gambar Bahan

Admin dapat membuat dan memperbarui data bahan. Form bahan harus mendukung foto/thumbnail agar kain, tinta printing, dan benang lebih mudah dikenali secara visual.

Field:
- kode bahan unik;
- nama bahan;
- kategori: kain, tinta printing, atau benang untuk versi pertama; kategori tambahan memerlukan penyesuaian kebutuhan dan skema database;
- satuan;
- ambang minimum stok;
- lokasi penyimpanan (opsional);
- foto bahan (opsional saat pembuatan, dapat ditambahkan atau diganti oleh Admin);
- catatan;
- status aktif/nonaktif.

Perilaku gambar:
- Jika Admin mengunggah foto, sistem menampilkan thumbnail pada daftar inventory dan foto yang lebih besar pada detail bahan.
- Jika foto belum tersedia, sistem menampilkan gambar placeholder sesuai kategori. Jangan menampilkan ikon foto rusak atau ruang gambar kosong.
- Contoh placeholder bawaan: `default-kain.webp`, `default-tinta.webp`, `default-benang.webp`, dan `default-material.webp`.
- Foto adalah foto bahan/material yang sebenarnya jika tersedia; placeholder hanya dipakai sebagai fallback dan tidak boleh seolah-olah mewakili varian/warna persis.
- Sistem menyediakan preview foto sebelum disimpan dan opsi mengganti/menghapus foto sehingga kembali memakai placeholder.
- Setelah file disimpan dan data database berhasil diperbarui, thumbnail harus tampil tanpa perlu mengubah source code.

Stok awal harus dibuat sebagai transaksi stok awal. Bahan yang pernah direferensikan oleh transaksi tidak dihapus permanen; bahan dapat dinonaktifkan.

### FR-08 — Pencatatan Pesanan Jersey

Admin dapat membuat pesanan dengan data:
- kode pesanan otomatis dan unik;
- nama pelanggan;
- kontak pelanggan;
- tanggal pesanan;
- target selesai (opsional);
- jenis/model jersey atau keterangan pesanan;
- jumlah pesanan;
- deskripsi kebutuhan/desain;
- catatan internal;
- status pesanan;
- Admin pembuat.

Sistem memvalidasi field wajib dan mencegah kode pesanan duplikat. Upload desain tidak wajib pada versi pertama; jika nanti dibutuhkan, fitur upload harus memiliki validasi keamanan khusus.

### FR-09 — Detail dan Perubahan Status Pesanan

Admin dapat melihat detail dan memperbarui status. Status awal yang disarankan:
1. Pesanan Masuk
2. Desain
3. Printing
4. Penjahitan
5. Finishing / QC
6. Selesai
7. Dibatalkan

Tahapan ini harus dikonfirmasi dengan alur nyata DR.SPORT. Setiap perubahan menyimpan status lama, status baru, waktu perubahan, pengguna, dan catatan opsional.

Pesanan yang dibatalkan tidak otomatis mengembalikan stok. Bahan yang benar-benar dikembalikan harus dicatat melalui transaksi return atau penyesuaian yang memiliki alasan.

### FR-10 — Pencatatan Stok Masuk

Admin mencatat nomor transaksi, bahan, jumlah, satuan, tanggal, asal/keterangan (opsional), catatan, dan pengguna pencatat.

Saat disimpan, sistem memvalidasi jumlah positif, memeriksa/mengunci saldo bahan, menambah stok, menyimpan transaksi masuk, serta mencatat saldo sebelum dan sesudah. Seluruh perubahan dilakukan dalam satu transaksi database. Bila salah satu proses gagal, semua perubahan di-rollback.

### FR-11 — Pencatatan Stok Keluar / Pemakaian

Admin mencatat bahan yang benar-benar digunakan atau dikeluarkan.

Field:
- nomor transaksi;
- bahan;
- jumlah;
- satuan;
- tanggal;
- pesanan terkait jika ada;
- alasan/keterangan;
- pengguna pencatat.

Aturan:
- jumlah harus positif;
- saldo diperiksa ulang tepat saat penyimpanan;
- stok tidak boleh minus;
- stok berkurang hanya jika transaksi berhasil disimpan;
- saldo dan ledger disimpan bersama dalam satu transaksi database;
- jika stok tidak cukup, sistem menolak transaksi dan menampilkan saldo tersedia.

### FR-12 — Pengecekan Ketersediaan oleh Karyawan

Karyawan dapat memilih bahan dan memasukkan jumlah yang diperlukan untuk memeriksa ketersediaan.

Hasil:
- **Tersedia:** saldo sama dengan atau lebih besar dari jumlah yang dibutuhkan dan di atas ambang minimum;
- **Stok terbatas:** stok tersedia, tetapi mencapai atau berada di bawah batas minimum;
- **Tidak cukup:** saldo lebih kecil dari jumlah yang dibutuhkan;
- **Habis:** saldo nol.

Pengecekan hanya membaca data dan **tidak mengurangi stok**. Pengurangan dilakukan saat Admin mencatat pemakaian/pengeluaran bahan.

### FR-13 — Inventory Real-Time

Makna operasional real-time pada versi ini:
- saldo stok di database berubah segera setelah transaksi masuk/keluar berhasil;
- halaman yang menjalankan transaksi menampilkan saldo baru setelah server mengonfirmasi keberhasilan;
- halaman pengguna lain dapat memperbarui data melalui AJAX polling pada interval wajar atau saat refresh;
- database, bukan nilai cache browser, menjadi sumber data utama;
- stok keluar selalu divalidasi ulang di server dengan transaksi database dan locking.

AJAX polling dapat memakai interval sekitar 5–15 detik sesuai performa. Sistem tidak boleh mengklaim menggunakan push instan/WebSocket jika belum diimplementasikan.

### FR-14 — Riwayat Inventory

Sistem menyimpan ledger untuk setiap pergerakan stok:
- bahan;
- tipe transaksi (masuk, keluar, penyesuaian, atau pengembalian bila digunakan);
- jumlah;
- saldo sebelum dan sesudah;
- referensi pesanan (opsional);
- keterangan;
- waktu transaksi;
- user pencatat.

Transaksi yang sudah tersimpan tidak dihapus melalui UI. Kesalahan dikoreksi melalui transaksi koreksi/penyesuaian dengan alasan dan jejak audit.

### FR-15 — Peringatan Stok Minimum

Admin dapat menetapkan batas minimum untuk setiap bahan. Jika saldo sama dengan atau di bawah batas tersebut, tampilkan peringatan pada dashboard dan halaman inventory beserta saldo saat ini serta batas minimum.

### FR-16 — Laporan Pesanan

Admin dan Super Admin dapat memfilter laporan berdasarkan rentang tanggal, status, kode pesanan, dan pelanggan sesuai kewenangan. Laporan menampilkan kode pesanan, tanggal, pelanggan, ringkasan, jumlah, status, dan target selesai jika dipakai. Laporan dapat dicetak melalui browser; ekspor file dapat ditambahkan jika dibutuhkan.

### FR-17 — Laporan Inventory

Admin dan Super Admin dapat melihat saldo saat ini, satuan, batas minimum, total stok masuk/keluar pada periode, saldo awal/akhir, dan riwayat transaksi per bahan. Perhitungan harus berdasarkan ledger, bukan hanya data tampilan.

### FR-18 — Log Aktivitas

Catat aktivitas penting, termasuk login, logout, pembuatan/nonaktif akun, pembuatan/perubahan pesanan, perubahan status, pembuatan master bahan, stok masuk/keluar, penyesuaian stok, dan akses ditolak untuk operasi sensitif bila relevan.

Log menyimpan pengguna, jenis aktivitas, ringkasan, waktu, dan metadata sumber yang diperlukan. Jangan menyimpan password atau token sesi di log.

### FR-19 — Profil dan Ganti Password

Pengguna dapat melihat profil, mengganti password setelah memasukkan password saat ini, dan logout. Logout harus menghancurkan sesi di server dan membersihkan cookie sesi.

## 9. Aturan Bisnis (Business Rules)

### BR-01 — Sumber data tunggal
Database menjadi sumber kebenaran untuk stok, pesanan, role, dan laporan.

### BR-02 — Kategori bahan
Inventory utama hanya mencakup kain, tinta printing, dan benang.

### BR-03 — Satuan konsisten
Setiap bahan memiliki satuan dasar yang konsisten. Sistem tidak boleh menjumlahkan bahan dengan satuan yang tidak setara. Konversi satuan tidak otomatis pada versi awal kecuali dirancang dan diuji.

### BR-04 — Tidak boleh ada stok negatif
Sistem menolak transaksi keluar jika jumlah diminta lebih besar daripada saldo tersedia.

### BR-05 — Cek stok tidak mengurangi stok
Aktivitas Karyawan melihat atau memeriksa bahan tidak mengubah saldo inventory.

### BR-06 — Pemakaian dicatat saat benar-benar terjadi
Stok keluar dicatat saat Admin merekam pengeluaran/pemakaian yang benar-benar dilakukan, bukan hanya saat pesanan dibuat atau stok diperiksa.

### BR-07 — Perubahan stok harus memiliki ledger
Saldo tidak boleh diedit diam-diam. Setiap perubahan harus memiliki transaksi dengan user, waktu, alasan, dan saldo sebelum/sesudah.

### BR-08 — Pemakaian terkait pesanan
Jika bahan digunakan untuk pesanan tertentu, transaksi stok keluar sebaiknya merujuk pesanan tersebut.

### BR-09 — Status awal pesanan
Pesanan baru mendapat status `Pesanan Masuk`.

### BR-10 — Riwayat dipertahankan
Data yang direferensikan oleh transaksi tidak dihapus permanen; gunakan status nonaktif atau pembatalan yang tercatat.

### BR-11 — Hak akses diperiksa di server
Menyembunyikan menu saja tidak cukup. Semua halaman dan aksi sensitif harus melalui pemeriksaan autentikasi dan otorisasi.

### BR-12 — Mutasi stok atomik
Pembaruan saldo dan insert ledger dijalankan dalam satu transaksi database dengan locking yang sesuai untuk mencegah dua proses menggunakan stok yang sama secara bersamaan.

## 10. Konsep UI/UX

### 10.1 Arah desain

**Design direction: Simple, Professional Inventory Console.** Antarmuka dibuat bersih, tenang, dan mudah dipindai saat digunakan untuk pekerjaan sehari-hari. Prioritasnya adalah keterbacaan data pesanan dan stok, bukan dekorasi.

Prinsip desain:
- Gunakan layout dashboard sederhana dengan sidebar, topbar, judul halaman, dan konten utama.
- Tabel menjadi tampilan utama untuk data operasional. Gunakan kartu hanya untuk ringkasan penting, bukan untuk setiap baris data.
- Dashboard cukup menampilkan maksimal empat indikator utama di bagian atas, kemudian tabel pesanan terbaru dan bahan yang perlu diperhatikan.
- Gunakan satu tombol aksi utama per halaman, misalnya **Tambah Bahan** atau **Tambah Pesanan**. Aksi sekunder tampil sebagai tombol outline atau menu tindakan.
- Hindari gradient besar, glassmorphism, bayangan tebal, animasi dekoratif, warna-warni berlebihan, badge yang terlalu banyak, dan emoji untuk ikon operasional.
- Gunakan foto bahan hanya di area yang membantu identifikasi: daftar inventory, hasil pencarian ketersediaan, dan detail bahan. Tidak perlu memasang foto besar pada setiap kartu dashboard.
- Foto ditampilkan dengan crop konsisten dan latar netral; foto yang berbeda rasio tidak boleh membuat baris tabel menjadi tidak rata.
- Empty state, pesan error, dan loading tampil sederhana dengan arahan tindakan yang jelas.

### 10.2 Warna

| Token | Hex | Penggunaan |
|---|---|---|
| Primary | `#2457A7` | Tombol utama, link aktif, fokus input |
| Sidebar | `#172033` | Navigasi samping yang tenang |
| Background | `#F5F7FA` | Latar area konten |
| Surface | `#FFFFFF` | Form, tabel, panel |
| Text | `#202938` | Teks utama |
| Muted | `#667085` | Metadata dan teks bantu |
| Success | `#237A4B` | Status berhasil/tersedia |
| Warning | `#A86407` | Stok terbatas/perlu perhatian |
| Danger | `#C0362C` | Stok habis/error/pembatalan |
| Border | `#E3E8EF` | Garis pemisah dan batas komponen |

Gunakan warna aksen hanya untuk tindakan utama dan status. Status harus selalu memiliki label teks; jangan mengandalkan warna saja. Latar konten tetap terang dan sebagian besar komponen menggunakan putih serta border tipis.

### 10.3 Tipografi dan komponen

- Gunakan font sistem yang mudah dibaca; Inter boleh dipakai bila tersedia, tetapi aplikasi tidak boleh rusak jika font eksternal gagal dimuat.
- Teks utama sekitar 14–16 px; judul halaman sekitar 22–26 px; label dan metadata tetap terbaca.
- Radius komponen sekitar 6–8 px. Gunakan shadow sangat ringan atau tanpa shadow.
- Jarak antarbagian konsisten; sisakan ruang kosong agar halaman tidak terasa padat.
- Tombol memiliki label teks yang jelas. Ikon Font Awesome hanya membantu pemahaman, bukan menggantikan label untuk aksi penting.
- Badge status menggunakan warna lembut dan ukuran kecil.
- Tabel memiliki pencarian/filter, pagination, empty state, dan loading/error state jika memakai AJAX.
- Pada layar kecil, tabel panjang dapat berubah menjadi baris/kartu ringkas, tetapi informasi nama bahan, gambar, stok, satuan, dan status harus tetap terlihat.

### 10.4 Layout AdminLTE

- Sidebar hanya memuat menu sesuai role, dengan label yang ringkas dan ikon seperlunya.
- Topbar berisi identitas DR.SPORT, judul/konteks halaman bila diperlukan, dan menu akun.
- Breadcrumb digunakan hanya untuk halaman bertingkat.
- Area konten berisi judul, keterangan singkat bila diperlukan, aksi utama, lalu tabel/form.
- Form dibagi menjadi kelompok logis tanpa terlalu banyak panel bersarang.
- Konsisten gunakan satu gaya tombol utama, satu gaya tombol sekunder, dan pola konfirmasi yang sama.

### 10.5 Spesifikasi foto bahan

- Daftar inventory dan hasil pengecekan Karyawan memakai thumbnail persegi **44 × 44 px** pada desktop, dengan sudut membulat ringan.
- Pada tampilan mobile, thumbnail dapat menggunakan ukuran **40 × 40 px** agar informasi stok tetap mendapatkan ruang yang cukup.
- Detail bahan menampilkan foto utama maksimal sekitar **160 × 160 px**, disertai nama, kategori, kode, stok, dan satuan.
- Gambar menggunakan `object-fit: cover` dan latar netral; tombol untuk membuka foto penuh hanya ditambahkan jika memang diperlukan.
- Sediakan placeholder berbeda menurut kategori bahan: kain, tinta printing, benang, dan placeholder umum.
- Thumbnail tidak boleh menjadi latar dekoratif tabel. Foto ditempatkan di kolom kecil di samping nama bahan untuk menjaga tampilan tetap rapi.
- Foto tidak diperlukan untuk pesanan atau akun pengguna pada versi pertama; fokus gambar hanya pada master inventory.

## 11. Spesifikasi Halaman UI

### 11.1 Login

Tampilkan logo/nama DR.SPORT, input username/email, password, tombol masuk, dan pesan error generik. Jangan tampilkan link registrasi publik atau mengungkap apakah akun tertentu terdaftar.

### 11.2 Dashboard Super Admin

Ringkasan akun aktif, jumlah Admin/Karyawan, jumlah pesanan per status, bahan stok rendah, dan aktivitas terbaru. Aksi cepat: tambah akun, daftar pesanan, dan inventory.

### 11.3 Manajemen Pengguna

Tabel berisi nama, username/email, role, status, terakhir login jika tersedia, dan aksi detail/edit/aktif-nonaktif/reset password. Jangan pernah menampilkan hash password.

### 11.4 Dashboard Admin

Ringkasan pesanan baru/diproses/selesai, stok per kategori, bahan stok rendah, dan transaksi inventory terbaru.

### 11.5 Daftar Pesanan

Kolom: kode pesanan, pelanggan, tanggal, target selesai, jumlah jersey, status, Admin pembuat, dan aksi. Sediakan pencarian, filter status/tanggal, pagination, serta empty state.

### 11.6 Detail Pesanan

Tampilkan informasi pesanan dan pelanggan yang diperlukan, deskripsi/model, jumlah, target selesai, status terkini, timeline status, serta catatan penggunaan bahan bila tersedia.

### 11.7 Daftar Inventory

Gunakan tabel yang ringkas sebagai tampilan utama. Kolom disarankan:

1. **Bahan** — thumbnail 44 × 44 px di samping nama bahan dan kode kecil di bawahnya;
2. kategori;
3. saldo dan satuan;
4. minimum stok;
5. status ketersediaan;
6. aksi.

Aturan tampilan:
- Urutan awal menampilkan bahan aktif; bahan nonaktif dapat diakses lewat filter.
- Sediakan pencarian nama/kode, filter kategori, filter status stok, dan pagination.
- Gunakan label status singkat seperti **Tersedia**, **Menipis**, dan **Habis** dengan warna lembut.
- Klik nama/thumbnail membuka detail bahan; aksi edit hanya tersedia untuk role yang berwenang.
- Pada layar kecil, tampilkan format baris responsif dengan thumbnail kecil, nama, saldo/satuan, status, dan menu aksi.
- Jangan membuat setiap bahan menjadi kartu besar karena akan membuat daftar panjang dan ramai.

### 11.8 Form Stok Masuk/Keluar

Field: bahan, saldo saat ini, jenis transaksi, jumlah, satuan (otomatis mengikuti bahan), tanggal, pesanan terkait untuk stok keluar jika ada, alasan/catatan, dan tombol simpan/batal. Tampilkan peringatan bila stok tidak cukup atau saldo akan menjadi di bawah minimum.

### 11.9 Halaman Cek Ketersediaan Karyawan

Sediakan pencarian dan filter kategori dengan tabel/daftar yang ringkas. Setiap item menampilkan thumbnail bahan, nama, kode, kategori, stok tersedia, satuan, dan status. Karyawan dapat memasukkan jumlah yang dibutuhkan untuk memeriksa apakah stok mencukupi.

- Gunakan thumbnail kategori atau foto bahan aktual yang sama dengan halaman inventory.
- Hasil pengecekan ditampilkan sebagai pesan sederhana: **Stok mencukupi** atau **Stok tidak mencukupi**, beserta saldo yang tersedia.
- Tampilkan waktu pembaruan terakhir jika tersedia.
- Tombol cek hanya membaca stok; tidak membuat transaksi dan tidak mengurangi saldo.
- Jangan tampilkan form pengelolaan bahan atau tombol mutasi stok kepada Karyawan.

### 11.10 Laporan

Sediakan filter tanggal dan kategori/status, tabel hasil, ringkasan periode, dan tombol cetak. Tampilkan periode laporan dengan jelas.

## 12. Rancangan Database

Rancangan ini perlu divalidasi sebelum pembuatan database final.

### 12.1 `users`

- `id`
- `name`
- `username` (unik)
- `email` (unik jika digunakan)
- `password_hash`
- `role` (`super_admin`, `admin`, `karyawan`)
- `status` (`active`, `inactive`)
- `last_login_at` (nullable)
- `password_changed_at` (nullable)
- `created_by` (nullable, FK ke users)
- `created_at`
- `updated_at`

### 12.2 `customers`

- `id`
- `name`
- `phone`
- `address` (nullable)
- `notes` (nullable)
- `created_at`
- `updated_at`

Pelanggan hanya dicatat untuk kebutuhan pesanan internal; mereka tidak harus memiliki akun sistem.

### 12.3 `orders`

- `id`
- `order_code` (unik)
- `customer_id` (FK)
- `order_date`
- `target_date` (nullable)
- `jersey_description`
- `quantity`
- `status`
- `notes` (nullable)
- `created_by` (FK users)
- `created_at`
- `updated_at`
- `cancelled_at` (nullable)

### 12.4 `order_status_history`

- `id`
- `order_id` (FK)
- `old_status` (nullable untuk status awal)
- `new_status`
- `note` (nullable)
- `changed_by` (FK users)
- `changed_at`

### 12.5 `materials`

- `id`
- `material_code` (unik)
- `name`
- `category` (`kain`, `tinta_printing`, `benang`)
- `unit`
- `current_stock` (DECIMAL, tidak boleh negatif)
- `minimum_stock` (DECIMAL)
- `location` (nullable)
- `notes` (nullable)
- `image_path` (nullable; path relatif file foto yang dikelola aplikasi)
- `is_active`
- `created_by` (FK users)
- `created_at`
- `updated_at`

`current_stock` merupakan saldo terkini untuk pembacaan cepat. Riwayat pergerakan harus tetap ada pada `inventory_transactions`. Perubahan saldo dan ledger harus dilakukan secara atomik. `image_path` menyimpan path relatif yang dibuat sistem; bila nilainya `NULL` atau file tidak tersedia, antarmuka menggunakan placeholder sesuai kategori.

### 12.6 `inventory_transactions`

- `id`
- `transaction_code` (unik)
- `material_id` (FK)
- `transaction_type` (`in`, `out`, `adjustment_in`, `adjustment_out`, `return` bila diperlukan)
- `quantity` (positif)
- `stock_before`
- `stock_after`
- `order_id` (nullable FK)
- `reason`
- `notes` (nullable)
- `created_by` (FK users)
- `created_at`

### 12.7 `activity_logs`

- `id`
- `user_id` (nullable FK, misalnya untuk login gagal)
- `action`
- `entity_type` (nullable)
- `entity_id` (nullable)
- `description`
- `ip_address` (opsional; mengikuti kebijakan privasi)
- `created_at`

### 12.8 Relasi utama

```text
users 1 ─── * orders           (created_by)
users 1 ─── * materials        (created_by)
users 1 ─── * inventory_transactions
users 1 ─── * activity_logs

customers 1 ─── * orders
orders 1 ─── * order_status_history
orders 1 ─── * inventory_transactions (opsional untuk pemakaian bahan)
materials 1 ─── * inventory_transactions
```

### 12.9 Index dan integritas

- Unique index untuk username, email jika diwajibkan, kode bahan, kode pesanan, dan kode transaksi.
- Index untuk status/tanggal pesanan, bahan pada transaksi, waktu transaksi, dan kategori bahan.
- Gunakan foreign key dan aturan penghapusan yang mempertahankan riwayat.
- Gunakan DECIMAL untuk jumlah stok yang dapat memiliki pecahan; jangan memakai FLOAT untuk saldo inventory.
- Hindari `DELETE CASCADE` untuk data pesanan atau ledger historis.

## 13. Keamanan Sistem

Keamanan harus dibangun sejak awal. Tidak ada aplikasi yang dapat dijamin bebas dari semua celah; target yang realistis adalah menerapkan kontrol berlapis dan mengujinya.

### 13.1 Autentikasi dan password

- Simpan password dengan `password_hash()` dan verifikasi memakai `password_verify()`.
- Jangan menyimpan password plaintext atau mencetaknya ke log.
- Terapkan kebijakan password yang wajar dan validasi server-side.
- Gunakan pesan login gagal yang generik.
- Terapkan rate limiting atau jeda setelah percobaan login berulang.
- Jalankan `session_regenerate_id(true)` setelah login berhasil.
- Logout menghancurkan sesi server dan membersihkan cookie sesi.
- Reset password menggunakan password sementara acak atau alur reset aman; minta pengguna mengganti password sementara pada login berikutnya.

### 13.2 Otorisasi

- Periksa sesi dan role pada setiap route/aksi PHP sensitif.
- Tolak akses bila role tidak berizin, termasuk ketika URL dimasukkan langsung.
- Jangan mempercayai role dari form, query string, atau JavaScript.
- Terapkan prinsip hak akses minimum.

### 13.3 SQL Injection

- Gunakan prepared statements PDO/MySQLi untuk semua input pengguna.
- Jangan menyusun query dengan menggabungkan input langsung ke SQL.
- Validasi nama kolom sort/filter dengan daftar yang diizinkan.

### 13.4 CSRF

- Gunakan token CSRF pada form yang mengubah data.
- Verifikasi token di server untuk create/update, perubahan status, dan mutasi stok.
- Jangan menggunakan request GET untuk mengubah data.

### 13.5 XSS

- Escape data saat ditampilkan ke HTML, misalnya `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- Jangan merender catatan sebagai HTML mentah.
- Validasi input di server sesuai konteks.

### 13.6 Keamanan sesi dan konfigurasi

- Cookie sesi memakai `HttpOnly`, `SameSite=Lax` atau kebijakan sesuai kebutuhan, dan `Secure` saat memakai HTTPS.
- Atur timeout idle yang wajar.
- Nonaktifkan tampilan error PHP kepada pengguna pada produksi; detail teknis disimpan di log yang tidak publik.
- Simpan kredensial database di konfigurasi yang tidak dimasukkan ke repository publik.
- Gunakan akun database dengan hak minimum saat deployment.
- Gunakan HTTPS jika aplikasi diakses melalui jaringan publik.

### 13.7 Konsistensi mutasi inventory

- Saldo dan ledger harus diperbarui dalam satu transaksi database.
- Gunakan locking yang sesuai, misalnya `SELECT ... FOR UPDATE`, atau operasi atomik setara.
- Verifikasi stok cukup setelah mendapatkan lock, bukan hanya dari nilai yang terlihat di browser.
- Tolak stok negatif.
- Jika insert ledger gagal, rollback perubahan saldo.
- Jangan izinkan edit/hapus transaksi inventory yang sudah diposting; gunakan transaksi koreksi yang tercatat.
- Uji dua transaksi stok keluar secara bersamaan pada stok terbatas.

### 13.8 Audit

- Catat siapa, kapan, dan tindakan apa yang mengubah data penting.
- Jangan menyediakan tombol hapus log untuk pengguna biasa.
- Jangan menyertakan password atau token sesi di log.

### 13.9 Validasi data

- Validasi di browser untuk kenyamanan dan di server untuk keamanan.
- Jumlah harus numerik dan lebih besar dari nol.
- Status harus berasal dari nilai yang diizinkan.
- Validasi tanggal dan relasi pesanan/bahan.

### 13.10 Keamanan unggah foto bahan

Fitur foto bahan merupakan bagian dari versi ini, sehingga kontrol berikut wajib diterapkan:

- Format yang diterima hanya JPG/JPEG, PNG, dan WEBP. Tolak SVG, HTML, PHP, dan format lain yang tidak diperlukan.
- Ukuran maksimal file **2 MB**. Validasi batas ukuran di sisi server, bukan hanya melalui atribut HTML.
- Validasi ekstensi yang diizinkan, MIME aktual menggunakan `finfo`, dan validitas gambar menggunakan `getimagesize()`; jangan mempercayai nama file atau `Content-Type` dari browser.
- Buat nama file acak dari server; jangan memakai nama asli unggahan sebagai nama penyimpanan. Simpan hanya path relatif yang dihasilkan sistem ke `materials.image_path`.
- Simpan gambar di direktori khusus, misalnya `uploads/materials/`, dan konfigurasi Apache agar file skrip tidak dapat dieksekusi di direktori tersebut.
- Tolak file gambar yang dimensinya tidak wajar atau tidak berhasil dibaca. Batas dimensi maksimal yang disarankan adalah 3000 × 3000 px.
- Batasi aksi unggah/ganti/hapus foto kepada Admin. Super Admin boleh memantau foto dan data bahan; Karyawan hanya dapat melihatnya.
- Lindungi aksi unggah dengan autentikasi, otorisasi, CSRF token, validasi server-side, dan audit log.
- Gunakan placeholder kategori jika foto tidak tersedia atau file lama gagal ditemukan; jangan sampai halaman menjadi rusak karena gambar hilang.
- Saat mengganti foto, hapus file lama hanya setelah perubahan database berhasil. Jangan menghapus file foto yang masih direferensikan bahan lain.
- Jangan mengandalkan URL gambar eksternal dari pengguna. Gunakan file lokal aplikasi agar tampilan konsisten dan tidak tergantung layanan pihak ketiga.

## 14. Definisi Inventory Real-Time

Agar istilah real-time akurat, sistem harus memenuhi perilaku berikut:

1. Setiap mutasi stok yang berhasil langsung memperbarui saldo di database.
2. Halaman yang menjalankan transaksi menampilkan saldo terbaru setelah server mengonfirmasi keberhasilan.
3. Pengguna lain melihat stok terkini saat refresh atau melalui polling AJAX pada interval yang ditetapkan.
4. Pengecekan stok tidak menjadikan cache browser sebagai sumber kebenaran.
5. Keputusan stok keluar divalidasi ulang di server dengan transaksi database dan locking.
6. Jika transaksi gagal, saldo tidak berubah dan pengguna mendapatkan pesan yang sesuai.

WebSocket/push notification tidak termasuk versi awal. AJAX polling dapat digunakan untuk memperbarui tampilan lintas pengguna.

## 15. Laporan dan Perhitungan

### Laporan pesanan

- total pesanan pada periode;
- jumlah pesanan per status;
- daftar pesanan dengan tanggal, jumlah jersey, dan status;
- daftar pesanan yang belum selesai.

### Laporan inventory

- saldo saat ini per bahan;
- total stok masuk dalam periode;
- total stok keluar dalam periode;
- saldo awal dan saldo akhir periode;
- riwayat mutasi per bahan.

### Definisi saldo

- **Saldo awal periode:** saldo sebelum waktu mulai laporan.
- **Stok masuk:** total transaksi masuk pada rentang laporan.
- **Stok keluar:** total transaksi keluar pada rentang laporan.
- **Saldo akhir periode:** saldo awal + stok masuk − stok keluar ± penyesuaian/pengembalian.

Perhitungan harus berasal dari ledger yang konsisten dan diuji dengan transaksi contoh.

## 16. Pengujian

Metode yang direkomendasikan: **Black Box Testing**, ditambah pengujian keamanan dan konsistensi stok.

| ID | Area | Skenario | Hasil yang diharapkan |
|---|---|---|---|
| T-01 | Login | Kredensial valid, akun aktif | Berhasil masuk ke dashboard role yang sesuai |
| T-02 | Login | Password salah | Ditolak dengan pesan generik |
| T-03 | Akun | Admin mencoba membuat akun lewat URL langsung | Akses ditolak |
| T-04 | Akun | Super Admin membuat username duplikat | Ditolak dengan validasi jelas |
| T-05 | Otorisasi | Karyawan membuka URL edit bahan | Akses ditolak di server |
| T-06 | Pesanan | Admin membuat pesanan lengkap | Pesanan tersimpan dengan kode unik |
| T-07 | Pesanan | Admin mengganti status | Status dan riwayat tersimpan |
| T-08 | Stok masuk | Jumlah positif | Saldo bertambah dan ledger tersimpan |
| T-09 | Stok keluar | Jumlah tidak melebihi stok | Saldo berkurang dan ledger tersimpan |
| T-10 | Stok keluar | Jumlah melebihi stok | Ditolak; saldo tetap |
| T-11 | Konkurensi | Dua pengeluaran bersamaan saat stok terbatas | Tidak ada stok negatif atau pengurangan ganda |
| T-12 | Karyawan | Mengecek bahan | Saldo tampil tanpa mutasi |
| T-13 | Minimum stok | Saldo mencapai batas minimum | Peringatan tampil |
| T-14 | CSRF | Mutasi tanpa token valid | Request ditolak |
| T-15 | SQL injection | Input mengandung sintaks SQL | Tidak mengubah query atau akses data |
| T-16 | XSS | Catatan mengandung script/tag | Ditampilkan sebagai teks aman |
| T-17 | Laporan | Filter periode tertentu | Data sesuai ledger dan filter |
| T-18 | Log | Admin mengubah pesanan/stok | Aktivitas tercatat beserta user dan waktu |
| T-19 | Responsif | Halaman dibuka di mobile | Layout tetap dapat digunakan |
| T-20 | Rollback | Ledger gagal disimpan | Perubahan saldo ikut dibatalkan |

## 17. Struktur Folder yang Disarankan

```text
drsport-inventory/
├── index.php
├── login.php
├── logout.php
├── config/
│   ├── database.php
│   └── app.php
├── includes/
│   ├── auth.php
│   ├── csrf.php
│   ├── helpers.php
│   ├── header.php
│   ├── footer.php
│   ├── navbar.php
│   └── sidebar.php
├── assets/
│   ├── css/
│   │   └── custom.css
│   ├── js/
│   │   └── app.js
│   └── images/
│       ├── logo-drsport.png
│       └── materials/
│           ├── default-kain.webp
│           ├── default-tinta.webp
│           ├── default-benang.webp
│           └── default-material.webp
├── uploads/
│   └── materials/
│       └── .htaccess  (mencegah eksekusi skrip; sesuaikan konfigurasi Apache)
├── superadmin/
│   ├── dashboard.php
│   ├── users/
│   ├── orders/
│   ├── inventory/
│   ├── reports/
│   └── activity-logs/
├── admin/
│   ├── dashboard.php
│   ├── orders/
│   ├── inventory/
│   └── reports/
├── karyawan/
│   ├── dashboard.php
│   ├── availability.php
│   ├── materials.php
│   └── orders.php
├── actions/
│   ├── user-actions.php
│   ├── order-actions.php
│   ├── inventory-actions.php
│   └── report-actions.php
├── database/
│   └── drsport_schema.sql
├── .gitignore
└── README.md
```

Struktur ini rekomendasi awal. Setiap aksi tetap harus melalui pemeriksaan otorisasi sisi server; pemisahan folder bukan pengganti kontrol akses.

## 18. Prioritas Implementasi

### Tahap 1 — Fondasi
1. Buat database dan tabel utama.
2. Buat koneksi PHP–MySQL.
3. Siapkan layout AdminLTE dan custom theme.
4. Implementasikan login/logout serta keamanan sesi.
5. Implementasikan pemeriksaan otorisasi role.

### Tahap 2 — Role dan dashboard
1. Dashboard Super Admin.
2. Manajemen akun Admin/Karyawan.
3. Dashboard Admin.
4. Dashboard Karyawan.
5. Uji akses tiap role.

### Tahap 3 — Master inventory
1. CRUD master bahan.
2. Placeholder foto per kategori dan upload/preview/ganti foto bahan.
3. Validasi file dan pengamanan direktori upload.
4. Stok awal lewat transaksi.
5. Stok masuk.
6. Stok keluar/pemakaian.
7. Ledger inventory dan locking transaksi.
8. Pengecekan ketersediaan oleh Karyawan.
9. Peringatan stok minimum.

### Tahap 4 — Pengelolaan pesanan
1. CRUD pesanan.
2. Detail pesanan.
3. Update status.
4. Riwayat perubahan status.
5. Hubungkan penggunaan bahan dengan pesanan.

### Tahap 5 — Laporan dan audit
1. Laporan pesanan.
2. Laporan inventory.
3. Log aktivitas.
4. Filter dan cetak laporan.

### Tahap 6 — Pengujian dan deployment
1. Black Box Testing.
2. Uji otorisasi.
3. Uji keamanan input.
4. Uji transaksi inventory bersamaan.
5. Uji responsivitas.
6. Backup database dan dokumentasi pemulihan.
7. Deployment di lingkungan yang disepakati.

## 19. Kriteria Penerimaan Akhir

- [ ] Terdapat role Super Admin, Admin, dan Karyawan dengan hak akses berbeda.
- [ ] Super Admin dapat membuat dan mengelola akun Admin/Karyawan.
- [ ] Admin dapat mengelola pesanan jersey serta master bahan.
- [ ] Karyawan dapat mengecek stok terkini tanpa mengubahnya.
- [ ] Inventory inti mencakup kain, tinta printing, dan benang.
- [ ] Daftar inventory menampilkan thumbnail untuk setiap bahan, memakai foto yang diunggah atau placeholder kategori.
- [ ] Admin dapat mengunggah, mengganti, dan menghapus foto bahan dengan validasi format, ukuran, dan keamanan file.
- [ ] Halaman cek ketersediaan Karyawan menampilkan thumbnail bahan tanpa memberikan izin untuk mengubahnya.
- [ ] UI menggunakan layout sederhana, tabel yang mudah dibaca, warna aksen terbatas, dan tetap responsif.
- [ ] Transaksi stok masuk/keluar memperbarui saldo dan ledger secara konsisten.
- [ ] Stok tidak menjadi negatif melalui transaksi aplikasi.
- [ ] Pengeluaran bahan dapat ditautkan ke pesanan bila relevan.
- [ ] Riwayat status pesanan dan transaksi inventory dapat ditelusuri.
- [ ] Dashboard dan laporan menggunakan data aktual dari database.
- [ ] Proteksi autentikasi, otorisasi, CSRF, SQL injection, XSS, dan sesi diterapkan.
- [ ] Password dan rahasia konfigurasi tidak masuk repository publik.
- [ ] Fitur inti diuji, termasuk akses langsung tanpa izin dan transaksi stok bersamaan.
- [ ] UI dapat digunakan di ukuran desktop dan mobile umum.
- [ ] Pembayaran online, pengiriman, manajemen keuangan, produksi otomatis, dan portal pelanggan tidak masuk versi pertama.

## 20. Risiko dan Keputusan yang Perlu Dikonfirmasi

Sebelum implementasi final, konfirmasikan dengan pihak DR.SPORT:

1. Satuan resmi untuk kain, tinta printing, dan benang.
2. Field wajib pada pesanan dan siapa yang boleh melihat kontak pelanggan.
3. Tahapan status pengerjaan yang benar-benar dipakai di workshop.
4. Kapan bahan dicatat keluar: saat diserahkan ke produksi atau saat pemakaian aktual.
5. Apakah Karyawan hanya mengecek stok atau juga perlu mengajukan permintaan bahan pada fase berikutnya.
6. Ambang minimum setiap bahan.
7. Siapa yang bertanggung jawab mengunggah foto bahan dan apakah foto asli tersedia untuk setiap varian.
8. Perlu tidaknya upload desain jersey pada tahap selanjutnya.

Tidak ada pengembangan yang dapat menjamin bebas dari semua celah secara mutlak. Target yang realistis adalah menerapkan kontrol keamanan berlapis, transaksi inventory yang konsisten, audit trail, pengujian, backup, dan pemeliharaan yang terdokumentasi.

## 21. Referensi terhadap Proposal

PRD ini mengikuti fokus proposal **Sistem Informasi Pengelolaan Pesanan Jersey Printing Berbasis Inventory Real-Time pada Konveksi DR.SPORT**: pengelolaan data pesanan, update status, monitoring stok bahan baku kain/tinta printing/benang, laporan pesanan, dan pengecualian pembayaran online, pengiriman, manajemen keuangan, serta produksi otomatis. Pembagian tiga role pada PRD merupakan penyesuaian berdasarkan kebutuhan terbaru pemilik proyek.
