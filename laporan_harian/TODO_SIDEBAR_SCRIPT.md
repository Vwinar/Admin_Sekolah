# Script untuk menambahkan admin-sidebar.js ke semua halaman admin

## Halaman Yang Sudah Ditambahkan ✅
1. admin_administrasi.php
2. admin_administrasi_managedata.php

## Halaman Yang Perlu Ditambahkan
Jalankan perintah berikut untuk find & replace di setiap file:

Cari:     `</script>\n</body>`
Ganti dengan: `</script>\n\n    <!-- Sidebar Toggle Script -->\n    <script src="../assets/admin-sidebar.js"></script>\n</body>`

### Daftar File:
- admin_catatan_khusus.php  
- admin_diary_ks.php
- admin_pembinaan_guru.php
- admin_supervisi_akademik.php
- admin_supervisi_admin.php
- admin_administrasi_notulen.php
- admin_master_plan.php
- admin_nilai_perkelas.php
- admin_analisis_akademik.php
- admin_statistik.php
- admin_settings.php
- dashboard_admin.php
- monitoring.php
- laporan.php
- rekap_absensi.php
- users.php

## Cara Manual:
1. Buka setiap file di editor
2. Cari tag penutup `</body>`
3. Tambahkan sebelumnya:
```html
<!-- Sidebar Toggle Script -->
<script src="../assets/admin-sidebar.js"></script>
```

## Catatan:
Script ini akan membuat sidebar:
- Auto-close di mobile saat page load
- Click toggle untuk buka/tutup
- Click outside sidebar = auto close (mobile only)
- Responsive window resize
