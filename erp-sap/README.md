# ERP Lite — Simulasi Proses Bisnis ala SAP

Aplikasi ERP edukasi berbasis **PHP + MySQL** yang mensimulasikan proses bisnis inti ala SAP.

## Modul & Proses Bisnis

| Modul | Halaman | Proses Bisnis |
|-------|---------|---------------|
| SD | `sales` | Sales Order → Delivery → Goods Issue → Billing → Payment (Order-to-Cash) |
| MM | `purchasing` | Purchase Requisition → Purchase Order → Goods Receipt → Invoice Verification → Payment (Procure-to-Pay) |
| PP | `production` | Production Order (CRTD → REL → CNF → DLV → TECO) |
| WM | `warehouse` | Stock & Goods Movement (101/102/201/261) |
| FI/CO | `finance` | Journal Umum, Piutang (AR), Utang (AP), Chart of Accounts |
| Master | `master` | Customer, Vendor, Material, Karyawan |

## Teknologi
- PHP 8+ (PDO)
- MySQL / MariaDB
- Bootstrap 5 + Bootstrap Icons
- Tampilan responsif (desktop/mobile)

## Instalasi
1. Import `database.sql` ke MySQL:
   ```bash
   mysql -u root -p < database.sql
   ```
2. Konfigurasi koneksi di `config/db.php` (default user `erp` / `erpsap123`).
3. Jalankan server:
   ```bash
   php -S 0.0.0.0:8000
   ```
4. Buka: `http://localhost:8000/index.php?page=dashboard`

## Konsep SAP yang disimulasikan
- Alur dokumen terintegrasi lintas modul (document flow)
- Penyusutan/pembaharuan stok otomatis saat Goods Issue/Receipt
- Postingan jurnal otomatis (FI/CO) pada setiap transaksi
- Master data terpusat (single source of truth)