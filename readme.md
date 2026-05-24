# Sistem Informasi Sekolah Terpadu (Eduvay)

Sistem Informasi Sekolah Terpadu (Eduvay) adalah platform manajemen sekolah cerdas berbasis web yang dirancang khusus untuk mempermudah kegiatan administrasi, pencatatan absen, serta dokumentasi pembelajaran. 

Aplikasi ini sangat praktis karena **menggunakan SQLite sebagai databasenya**. Artinya, Anda tidak perlu repot melakukan instalasi server database (seperti MySQL atau MariaDB). Anda cukup mengunggah file-file ini ke hosting, dan aplikasi siap digunakan seketika!

---

## 🌟 Fitur Utama

- 📝 **Laporan Harian:** Dokumentasi aktivitas guru secara digital dengan pelaporan terstruktur.
- 📍 **Absensi Geotagging:** Presensi modern menggunakan koordinat lokasi GPS untuk memastikan kedisiplinan guru dan siswa.
- 📚 **Literasi & Numerasi (Linum):** Platform pencatatan buku dan rangkuman siswa.
- 👥 **Administrasi Kelas:** Kelola data siswa dan jadwal pelajaran dalam satu dashboard.
- 📈 **Monitoring & Analitik:** Pantau perkembangan kinerja dengan visualisasi data admin.
- 📓 **Jurnal Mengajar:** Rekam jejak materi pembelajaran harian.

---

## 🚀 Cara Instalasi dan Hosting (Sangat Mudah!)

Karena aplikasi ini menggunakan **SQLite**, proses *deployment* ke cPanel atau hosting manapun sangatlah cepat. Berikut adalah panduannya:

### 1. Persiapan File
1. Jadikan seluruh folder `xxxx` (tempat file `index.php` berada) menjadi satu file berformat `.zip`.

### 2. Upload ke cPanel / Shared Hosting
1. Login ke panel hosting Anda (misal: **cPanel**).
2. Buka menu **File Manager**.
3. Navigasikan ke direktori **`public_html`** (atau direktori domain/subdomain yang Anda inginkan).
4. Klik tombol **Upload**, lalu pilih file `.zip` yang sudah Anda siapkan tadi.
5. Setelah proses upload selesai, klik kanan pada file `.zip` tersebut dan pilih **Extract**.
6. Pindahkan file-file dari dalam folder `xxx` langsung ke dalam `public_html` (jika Anda ingin aplikasi diakses lewat domain utama).

### 3. Konfigurasi Permission (Sangat Penting untuk SQLite)
Database SQLite berupa sebuah file fisik, sehingga web server (PHP) membutuhkan izin untuk **Membaca (Read)** dan **Menulis (Write)** pada file database tersebut *beserta folder tempatnya berada*.
1. Di cPanel, cari folder yang memuat file database SQLite Anda (biasanya ekstensi `.db` atau `.sqlite`).
2. Pastikan file database tersebut memiliki **Permission `0664` atau `0666`**.
3. Pastikan juga **folder** tempat database itu berada memiliki **Permission `0755` atau `0775`** agar PHP dapat membuat file *journal* sementara saat menulis ke database.
4. Atur juga *Permission* untuk folder `uploads` (misal: `laporan_harian/uploads`) menjadi `0755` atau `0775` agar user bisa mengunggah foto/file PDF dengan lancar.

### 4. Selesai! 🎉
Buka domain/subdomain Anda di browser. Aplikasi langsung bisa digunakan tanpa perlu repot import database SQL melalui phpMyAdmin!

---

## 🛠️ Teknologi yang Digunakan
- **Backend:** PHP Terapan (Native)
- **Database:** SQLite (Portable Database)
- **Frontend:** HTML5, CSS3 (Custom Design & Bootstrap 5), Vanilla JavaScript
- **Iconografi:** FontAwesome & Bootstrap Icons
- **Animasi:** AOS (Animate On Scroll)

---

## ⚠️ Keamanan Database SQLite
Karena SQLite berbasis file, pastikan folder tempat database berada dilindungi agar tidak dapat diunduh (download) langsung dari URL publik. Secara *default*, umumnya server modern sudah memblokir akses ke ekstensi file `.db` dan `.sqlite`, namun menambahkan `.htaccess` pelindung di dalam folder database selalu disarankan:

Buat file `.htaccess` di folder tempat SQLite berada:
```apache
<FilesMatch "\.(sqlite|db)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

---
*Dibuat untuk memajukan pendidikan yang lebih efisien dan terdigitalisasi.*
