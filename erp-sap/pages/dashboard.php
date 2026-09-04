<?php
// ============================================================
// Dashboard - KPI overview & business process map
// ============================================================
$pdo = db();

$kpi = [];
$kpi['sales'] = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE status NOT IN ('DRAFT','CANCELLED')")->fetchColumn();
$kpi['po'] = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM purchase_orders WHERE status NOT IN ('CANCELLED')")->fetchColumn();
$kpi['stock'] = (int)$pdo->query("SELECT COALESCE(SUM(stock),0) FROM materials")->fetchColumn();
$kpi['material'] = (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
$kpi['open_so'] = (int)$pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status IN ('CONFIRMED','DELIVERED','INVOICED')")->fetchColumn();
$kpi['open_po'] = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('OPEN','RECEIVED','INVOICED')")->fetchColumn();
$kpi['cust'] = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$kpi['vend'] = (int)$pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();

// Recent documents
$recent_so = $pdo->query("SELECT s.*, c.name AS customer_name FROM sales_orders s JOIN customers c ON c.id = s.customer_id ORDER BY s.id DESC LIMIT 5")->fetchAll();
$recent_po = $pdo->query("SELECT p.*, v.name AS vendor_name FROM purchase_orders p JOIN vendors v ON v.id = p.vendor_id ORDER BY p.id DESC LIMIT 4")->fetchAll();
$low_stock = $pdo->query("SELECT * FROM materials WHERE stock <- reorder_level ORDER BY stock ASC LIMIT 6")->fetchAll();
$recent_mv = $pdo->query("SELECT m.*, mat.name AS material_name, mat.code AS material_code FROM goods_movements m JOIN materials mat ON mat.id = m.material_id ORDER BY m.id DESC LIMIT 6")->fetchAll();
?>

<!-- ============ KPI Cards ============ -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card bg-erp-blue">
            <i class="bi bi-graph-up-arrow stat-icon"></i>
            <div class="stat-label">Total Penjualan (SD)</div>
            <div class="stat-value"><?= nf($kpi['sales']) ?></div>
            <div class="stat-sub">Semua status aktif</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card bg-erp-teal">
            <i class="bi bi-bag-check stat-icon"></i>
            <div class="stat-label">Total Pembelian (MM)</div>
            <div class="stat-value"><?= nf($kpi['po']) ?></div>
            <div class="stat-sub">Purchase Order aktif</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card bg-erp-orange">
            <i class="bi bi-box-seam stat-icon"></i>
            <div class="stat-label">Total Stok (WM)</div>
            <div class="stat-value"><?= number_format($kpi['stock']) ?></div>
            <div class="stat-sub"><?= $kpi['material'] ?> jenis material</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="stat-card bg-erp-purple">
            <i class="bi bi-arrow-left-right stat-icon"></i>
            <div class="stat-label">Dokumen Aktif</div>
            <div class="stat-value"><?= $kpi['open_so'] + $kpi['open_po'] ?></div>
            <div class="stat-sub"><?= $kpi['open_so'] ?> SO terbuka + <?= $kpi['open_po'] ?> PO terbuka</div>
        </div>
    </div>
</div>

<!-- ============ Process Map ============ -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-diagram-3 me-2 text-primary"></i> Bussiness Process Map — Order-to-Cash & Procure-to-Pay
            </div>
            <div class="card-body">
                <div class="process-flow">
                    <div class="process-step done"><span class="step-dot"></span> Sales Order</div>
                    <div class="process-arrow"><i class="bi bi-chevron-right"></i></div>
                    <div class="process-step done"><span class="step-dot"></span> Delivery</div>
                    <div class="process-arrow"><i class="bi bi-chevron-right"></i></div>
                    <div class="process-step current"><span class="step-dot"></span> Goods Issue</div>
                    <div class="process-arrow"><i class="bi bi-chevron-right"></i></div>
                    <div class="process-step"><span class="step-dot"></span> Billing</div>
                    <div class="process-arrow"><i class="bi bi-chevron-right"></i></div>
                    <div class="process-step"><span class="step-dot"></span> Payment (FI)</div
                    <span class="ms-2 text-muted small">Order-to-Cash</span>
                    <div class="vr mx-2 d-none d-md-block"></div>
                    <div class="process-step"><span class="step-dot"></span> PR → PO (MM)</div>
                    <div class="process-arrow"><i class="bi bi-chevron-right"></i></div>
                    <div class="process-step"><span class="step-dot"></span> Goods Receipt</div>
                    <div class="process-arrow"><i class="bi bi-chevron-right"></i></div>
                    <div class="process-step"><span class="step-dot"></span> Invoice Verification</div>
                    <div class="process-arrow"><i class="bi bi-chevron-right"></i></div>
                    <div class="process-step"><span class="step-dot"></span> Payment Out (FI)</div
                    <span class="ms-2 text-muted small">Procure-to-Pay</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ Recent Documents ============ -->
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-cart3 me-2 text-primary"></i> Sales Order Terbaru
                <a href="index.php?page=sales" class="btn btn-sm btn-outline-erp ms-auto">Kelola</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>No. SO</th><th>Customer</th><th>Tanggal</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$recent_so): ?>
                        <tr><td colspan="5"><div class="erp-empty"><i class="bi bi-inbox"></i>Belum ada sales order</div></td></tr>
                    <?php else: foreach ($recent_so as $so): ?>
                        <tr>
                            <td class="text-mono"><?= esc($so['so_number']) ?></td>
                            <td><?= esc($so['customer_name']) ?></td>
                            <td><?= date('d M Y', strtotime($so['order_date'])) ?></td>
                            <td class="text-end fw-semibold"><?= nf($so['total_amount']) ?></td>
                            <td><?= badge($so['status']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="row g-3 h-100">
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle me-2 text-danger"></i> Stok Menipis
                        <a href="index.php?page=warehouse" class="btn btn-sm btn-outline-erp ms-auto">Cek</a>
                    </div>
                    <div class="card-body py-2">
                        <ul class="list-erp">
                            <?php if (!$low_stock): ?>
                                <li class="text-success"><i class="bi bi-check-circle me-2"></i>Semua stok aman</li>
                            <?php else: foreach ($low_stock as $m): ?>
                                <li>
                                    <span><span class="text-mono"><?= esc($m['code']) ?></span> — <?= esc($m['name']) ?></span>
                                    <span class="badge bg-danger"><?= $m['stock'] ?> / <?= $m['reorder_level'] ?></span>
                                </li>
                            <?php endforeach; endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <i class="bi bi-arrow-repeat me-2 text-primary"></i> Pergerakan Stok Terakhir (MM/WM)
                    </div>
                    <div class="card-body py-2">
                        <ul class="list-erp">
                            <?php if (!$recent_mv): ?>
                                <li class="text-muted">Belum ada pergerakan</li>
                            <?php else: foreach ($recent_mv as $mv): ?>
                                <li>
                                    <span><span class="badge bg-light border text-dark"><?= esc($mv['movement_type']) ?></span> <?= esc($mv['material_code']) ?> <?= $mv['quantity'] ?> unit</span>
                                    <small class="text-muted"><?= esc($mv['reference']) ?></small>
                                </li>
                            <?php endforeach; endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ PO List ============ -->
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-bag-check me-2 text-success"></i> Purchase Order Terbaru
                <a href="index.php?page=purchasing" class="btn btn-sm btn-outline-erp ms-auto">Kelola</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>No. PO</th><th>Vendor</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$recent_po): ?>
                        <tr><td colspan="4"><div class="erp-empty"><i class="bi bi-inbox"></i>Belum ada PO</div></td></tr>
                    <?php else: foreach ($recent_po as $po): ?>
                        <tr>
                            <td class="text-mono"><?= esc($po['po_number']) ?></td>
                            <td><?= esc($po['vendor_name']) ?></td>
                            <td class="text-end fw-semibold"><?= nf($po['total_amount']) ?></td>
                            <td><?= badge($po['status']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-grid me-2 text-primary"></i> Modul Bisnis ERP</div>
            <div class="card-body">
                <div class="row g-2">
                    <?php
                    $mods = [
                        ['bi-cart3', 'SD', 'Sales & Distribution', 'sales'],
                        ['bi-bag-check', 'MM', 'Materials Management', 'purchasing'],
                        ['bi-gear-wide-connected', 'PP', 'Production Planning', 'production'],
                        ['bi-boxes', 'WM', 'Warehouse Management', 'warehouse'],
                        ['bi-cash-stack', 'FI/CO', 'Finance & Accounting', 'finance'],
                    ];
                    foreach ($mods as $mod):
                        ?>
                        <div class="col-md-4 col-6">
                            <a href="index.php?page=<?= $mod[3] ?>" class="text-decoration-none">
                                <div class="border rounded-3 p-3 text-center h-100 hover-shadow" style="transition:.2s;">
                                    <i class="bi <?= $mod[0] ?> d-block mb-1 text-primary" style="font-size:1.5rem;"></i>
                                    <div class="fw-bold small"><?= $mod[1] ?></div>
                                    <div class="text-muted" style="font-size:.7rem;"><?= $mod[2] ?></div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>