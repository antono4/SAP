-- ============================================================
-- ERP Lite -- SAP-style business process simulator
-- Database schema + seed data
-- ============================================================
CREATE DATABASE IF NOT EXISTS erp_sap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erp_sap;

-- ---------- Master Data ----------
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(120),
  phone VARCHAR(40),
  address TEXT,
  credit_limit DECIMAL(15,2) DEFAULT 10000000.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(120),
  phone VARCHAR(40),
  address TEXT,
  payment_terms INT DEFAULT 30,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  type ENUM('FERT','HALB','ROH') DEFAULT 'FERT',
  unit VARCHAR(20) DEFAULT 'PC',
  price DECIMAL(15,2) DEFAULT 0,
  stock INT DEFAULT 0,
  reorder_level INT DEFAULT 10,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  emp_no VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  position VARCHAR(80),
  department VARCHAR(80),
  base_salary DECIMAL(15,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE') DEFAULT 'ASSET',
  balance DECIMAL(15,2) DEFAULT 0
);

-- ---------- Transactional ----------
CREATE TABLE IF NOT EXISTS sales_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  so_number VARCHAR(30) NOT NULL UNIQUE,
  customer_id INT NOT NULL,
  order_date DATE NOT NULL,
  status ENUM('DRAFT','CONFIRMED','DELIVERED','INVOICED','PAID','CANCELLED') DEFAULT 'CONFIRMED',
  total_amount DECIMAL(15,2) DEFAULT 0,
  FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS sales_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  so_id INT NOT NULL,
  material_id INT NOT NULL,
  quantity INT DEFAULT 1,
  price DECIMAL(15,2) DEFAULT 0,
  subtotal DECIMAL(15,2) DEFAULT 0,
  FOREIGN KEY (so_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES materials(id)
);

CREATE TABLE IF NOT EXISTS deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  so_id INT NOT NULL,
  delivery_number VARCHAR(30) NOT NULL UNIQUE,
  delivery_date DATE,
  status ENUM('OPEN','PICKED','GOODS_ISSUED') DEFAULT 'OPEN',
  FOREIGN KEY (so_id) REFERENCES sales_orders(id)
);

CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  so_id INT NOT NULL,
  invoice_number VARCHAR(30) NOT NULL UNIQUE,
  invoice_date DATE,
  amount DECIMAL(15,2) DEFAULT 0,
  status ENUM('UNPAID','PAID') DEFAULT 'UNPAID',
  paid_date DATE NULL,
  FOREIGN KEY (so_id) REFERENCES sales_orders(id)
);

CREATE TABLE IF NOT EXISTS purchase_requisitions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pr_number VARCHAR(30) NOT NULL UNIQUE,
  material_id INT NOT NULL,
  quantity INT DEFAULT 1,
  vendor_id INT NULL,
  status ENUM('NEW','APPROVED','ORDERED') DEFAULT 'NEW',
  requested_date DATE,
  FOREIGN KEY (material_id) REFERENCES materials(id),
  FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);

CREATE TABLE IF NOT EXISTS purchase_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(30) NOT NULL UNIQUE,
  vendor_id INT NOT NULL,
  material_id INT NOT NULL,
  quantity INT DEFAULT 1,
  unit_price DECIMAL(15,2) DEFAULT 0,
  total_amount DECIMAL(15,2) DEFAULT 0,
  status ENUM('OPEN','RECEIVED','INVOICED','PAID','CANCELLED') DEFAULT 'OPEN',
  order_date DATE,
  FOREIGN KEY (vendor_id) REFERENCES vendors(id),
  FOREIGN KEY (material_id) REFERENCES materials(id)
);

CREATE TABLE IF NOT EXISTS goods_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  material_id INT NOT NULL,
  movement_type ENUM('101','102','201','261') NOT NULL COMMENT '101 GR, 102 GR-return, 201 GI,  261 prod-issue',
  quantity INT NOT NULL,
  reference VARCHAR(60),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (material_id) REFERENCES materials(id)
);

CREATE TABLE IF NOT EXISTS production_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(30) NOT NULL UNIQUE,
  material_id INT NOT NULL,
  planned_qty INT DEFAULT 1,
  status ENUM('CRTD','REL','CNF','DLV','TECO') DEFAULT 'CRTD' COMMENT 'CRTD=created REL=released CNF=confirmed DLV=delivered TECO=technically completed',
  start_date DATE,
  FOREIGN KEY (material_id) REFERENCES materials(id)
);

CREATE TABLE IF NOT EXISTS journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_number VARCHAR(30) NOT NULL UNIQUE,
  account_id INT NOT NULL,
  debit DECIMAL(15,2) DEFAULT 0,
  credit DECIMAL(15,2) DEFAULT 0,
  description VARCHAR(200),
  post_date DATE,
  FOREIGN KEY (account_id) REFERENCES accounts(id)
);

-- ---------- Seed Data ----------
INSERT IGNORE INTO accounts (code, name, type) VALUES
('100000','Kas & Bank','ASSET'),
('110000','Piutang Usaha','ASSET'),
('120000','Persediaan Barang Dagang','ASSET'),
('200000','Utang Usaha','LIABILITY'),
('300000','Modal','EQUITY'),
('400000','Penjualan','REVENUE'),
('410000','HPP','EXPENSE'),
('500000','Beban Operasional','EXPENSE');

INSERT IGNORE INTO customers (id, name, email, phone, address, credit_limit) VALUES
(1,'PT Maju Jaya','cs@majujaya.co.id','021-555-0101','Jl. Sudirman No. 45, Jakarta','250000000'),
(2,'CV Sentosa Abadi','hello@sentosaabadi.com','022-555-0102','Jl. Asia Afrika No.  12, Bandung','150000000'),
(3,'Toko Berkah','berkah.toko@gmail.com','031-555-0103','Jl. Diponegoro No.  8, Surabaya','75000000');

INSERT IGNORE INTO vendors (id, name, email, phone, payment_terms) VALUES
(1,'PT Sumber Rejeki','sales@sumberrejeki.co.id','021-555-0201',30),
(2,'CV Mitra Niaga','order@mitraniaga.com','061-555-0202',45),
(3,'UD Karya Makmur','admin@karyamakmur.co.id','024-555-0203',30);

INSERT IGNORE INTO materials (id, code, name, type, unit, price, stock, reorder_level) VALUES
(1,'F-1001','Laptop ThinkPad X1','FERT','UNIT',15000000.00,25,10),
(2,'F-1002','Monitor 24" IPS','FERT','UNIT',2500000.00,40,15),
(3,'F-1003','Keyboard Mechanical','FERT','UNIT',750000.00,60,20),
(4,'F-1004','Mouse Wireless','FERT','UNIT',250000.00,80,25),
(5,'R-2001','Motherboard','ROH','UNIT',3000000.00,30,10),
(6,'R-2002','Panel LCD 24"','ROH','UNIT',1200000.00,35,12),
(7,'R-2003','Mekanikal Switch (per 10pcs)','ROH','PACK',150000.00,50,20),
(8,'R-2004','Sensor Optical','ROH','UNIT',50000.00,100,30);

INSERT IGNORE INTO employees (id, emp_no, name, position, department, base_salary) VALUES
(1,'EMP-0001','Andi Wijaya','Sales Manager','Sales',15000000),
(2,'EMP-0002','Budi Santoso','Purchasing Officer','Procurement',11000000),
(3,'EMP-0003','Citra Lestari','Finance Staff','Finance',13000000),
(4,'EMP-0004','Dewi Anggraini','Warehouse Keeper','Warehouse',8500000);

-- Sample sales orders fixture
INSERT IGNORE INTO sales_orders (id, so_number, customer_id, order_date, status, total_amount) VALUES
(1,'SO-2026-0001',1,'2026-09-01','PAID',18750000.00),
(2,'SO-2026-0002',2,'2026-09-02','CONFIRMED',8250000.00);

INSERT IGNORE INTO sales_order_items (so_id, material_id, quantity, price, subtotal) VALUES
(1,1,1,15000000,15000000),
(1,3,5,750000,3750000),
(2,2,3,2500000,7500000),
(2,4,3,250000,750000);

-- Sample purchase orders
INSERT IGNORE INTO purchase_orders (id, po_number, vendor_id, material_id, quantity, unit_price, total_amount, status, order_date) VALUES
(1,'PO-2026-0001',1,5,20,3000000,60000000,'OPEN','2026-08-28'),
(2,'PO-2026-0002',2,6,15,1200000,18000000,'OPEN','2026-08-30');

-- Sample production orders
INSERT IGNORE INTO production_orders (id, order_number, material_id, planned_qty, status, start_date) VALUES
(1,'PRD-2026-0001',1,5,'CNF','2026-09-01'),
(2,'PRD-2026-0002',2,10,'REL','2026-09-03');

-- Sample journal entries
INSERT IGNORE INTO journal_entries (entry_number, account_id, debit, credit, description, post_date) VALUES
('JE-2026-0001',2,18750000,0,'Invoice SO-2026-0001','2026-09-01'),
('JE-2026-0002',5,0,18750000,'Penjualan SO-2026-0001','2026-09-01'),
('JE-2026-0003',3,60000000,0,'Penerimaan material PO-2026-0001','2026-08-28'),
('JE-2026-0004',4,0,60000000,'Utang PO-2026-0001','2026-08-28');