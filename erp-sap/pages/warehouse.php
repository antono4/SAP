<?php
// ============================================================
// Warehouse & Stock (WM) - Stock overview & movements
// ============================================================
$pdo = db();
$materials = $pdo->query("SELECT * FROM materials ORDER BY stock ASC")->fetchAll();
$moves = $pdo->query("SELECT m.*,mat.code AS material_code,mat.name AS material_name FROM goods_movements m JOIN materials mat ON mat.id=m.material_id ORDER BY m.id DESC LIMIT 40")->fetchAll();
$totalStock = (int)$pdo->query("SELECT COALESCE(SUM(stock),0) FROM materials")->fetchColumn();
$totalValue = (float)$pdo->query("SELECT COALESCE(SUM(stock*price),0) FROM materials")->fetchColumn();
?>

<div class="row g-3 mb-4">
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat-card bg-erp-blue">
      <i class="bi bi-boxes stat-icon"></i>
      <div class="stat-label">Total Stok</div>
      <div class="stat-value"><?= number_format($totalStock) ?></div>
      <div class="stat-sub">Semua material</div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat-card bg-erp-teal">
      <i class="bi bi-cash-coin stat-icon"></i>
      <div class="stat-label">Nilai Persediaan</div>
      <div class="stat-value"><?= nf($totalValue) ?></div>
      <div class="stat-sub">Berdasarkan harga master</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-xl-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-box-seam me-2 text-primary"></i> Stock Material (MM/WM</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th class="text-end">Stok</th><th class="text-end">Harga</th><th class="text-end">Nilai</th></tr></thead>
          <tbody>
          <?php foreach ($materials as $m): ?>
            <?php $low = (int)$m['stock'] <= (int)$m['reorder_level']; ?>
            <tr>
              <td class="text-mono"><?= esc($m['code']) ?></td>
              <td><?= esc($m['name']) ?></td>
              <td><span class="badge bg-light border text-dark"><?= esc($m['type']) ?></span></td>
              <td class="text-end">
                <?php if ($low): ?>
                  <span class="badge bg-danger"><?= (int)$m['stock'] ?></span>
                <?php else: ?>
                  <span class="badge bg-success"><?= (int)$m['stock'] ?></span>
                <?php endif; ?>
              </td>
              <td class="text-end"><?= nf($m['price']) ?></td>
              <td class="text-end fw-semibold"><?= nf((float)$m['stock'] * (float)$m['price']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-arrow-repeat me-2 text-primary"></i> Pergerakan Stok (Goods Movement</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Material</th><th>Tipe</th><th class="text-end">Qty</th><th>Referensi</th></tr></thead>
          <tbody>
          <?php if (!$moves): ?>
            <tr><td colspan="4"><div class="erp-empty"><i class="bi bi-arrow-repeat"></i>Belum ada pergerakan</div></td></tr>
          <?php else: foreach ($moves as $mv): ?>
            <tr>
              <td><span class="text-mono"><?= esc($mv['material_code']) ?></span> — <?= esc($mv['material_name']) ?></td>
              <td><span class="badge bg-light border text-dark"><?= esc($mv['movement_type']) ?></span></td>
              <td class="text-end"><?= (int)$mv['quantity'] ?></td>
              <td class="text-mono"><?= esc($mv['reference']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>