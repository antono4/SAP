<?php
// ============================================================
// Production Planning (PP) - Production Orders
// ============================================================
$pdo = db();
$msg = '';
$err = '';

if (isset($_GET['action'])) {
    $act = $_GET['action'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    try {
        if ($act === 'release') {
            $pdo->prepare("UPDATE production_orders SET status='REL' WHERE id=?")->execute([$id]);
            $msg = 'Production order direlease (REL.';
        }
        if ($act === 'confirm') {
            $pdo->prepare("UPDATE production_orders SET status='CNF' WHERE id=?")->execute([$id]);
            $msg = 'Production order dikonfirmasi (CNF.';
        }
        if ($act === 'deliver') {
            $pdo->prepare("UPDATE production_orders SET status='DLV' WHERE id=?")->execute([$id]);
            $msg = 'Production order di-deliver (DLV.';
        }
        if ($act === 'teco') {
            $pdo->prepare("UPDATE production_orders SET status='TECO' WHERE id=?")->execute([$id]);
            $msg = 'Production order ditutup teknis (TECO.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    try {
        if ($action === 'create') {
            $mid = (int)$_POST['material_id'];
            $qty = (int)$_POST['quantity'];
            $date = $_POST['start_date'];
            if ($mid <= 0) { throw new Exception('Pilih material.'); }
            if ($qty <= 0) { throw new Exception('Jumlah harus lebih dari  0.'); }
            $no = doc_number('PRD');
            $st = $pdo->prepare("INSERT INTO production_orders (order_number,material_id,planned_qty,status,start_date) VALUES (?,?,?,?,?)");
            $st->execute([$no,$mid,$qty,'CRTD',$date]);
            $msg = 'Production order ' . $no . ' dibuat (CRTD.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$orders = $pdo->query("SELECT o.*,m.code AS material_code,m.name AS material_name FROM production_orders o JOIN materials m ON m.id=o.material_id ORDER BY o.id DESC")->fetchAll();
$materials = $pdo->query("SELECT * FROM materials WHERE type='FERT' OR type='HALB' ORDER BY name")->fetchAll();
?>

<?php if ($msg): ?>
  <div class="alert alert-success alert-dismissible fade show" data-auto-dismiss><i class="bi bi-check-circle me-2"></i><?= esc($msg) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i><?= esc($err) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header d-flex align-items-center">
    <i class="bi bi-gear-wide-connected me-2 text-primary"></i> Production Order (PP
    <button class="btn btn-erp btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#modalPRD"><i class="bi bi-plus-lg me-1"></i>Buat Production Order</button>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>No. Order</th><th>Material</th><th class="text-end">Plan Qty</th><th>Mulai</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="6"><div class="erp-empty"><i class="bi bi-inbox"></i>Belum ada production order</div></td></tr>
      <?php else: foreach ($orders as $o): ?>
        <tr>
          <td class="text-mono"><?= esc($o['order_number']) ?></td>
          <td><span class="text-mono"><?= esc($o['material_code']) ?></span> — <?= esc($o['material_name']) ?></td>
          <td class="text-end"><?= (int)$o['planned_qty'] ?></td>
          <td><?= esc($o['start_date']) ?></td>
          <td><?= badge($o['status']) ?></td>
          <td class="text-end">
            <?php if ($o['status'] === 'CRTD'): ?>
              <a href="index.php?page=production&action=release&id=<?= $o['id'] ?>" class="btn btn-outline-erp btn-action" onclick="return confirm('Release?')"><i class="bi bi-play me-1"></i>Release</a>
            <?php elseif ($o['status'] === 'REL'): ?>
              <a href="index.php?page=production&action=confirm&id=<?= $o['id'] ?>" class="btn btn-outline-erp btn-action" onclick="return confirm('Konfirmasi produksi?')"><i class="bi bi-check2 me-1"></i>Confirm</a>
            <?php elseif ($o['status'] === 'CNF'): ?>
              <a href="index.php?page=production&action=deliver&id=<?= $o['id'] ?>" class="btn btn-outline-erp btn-action text-success" onclick="return confirm('Deliver ke gudang?')"><i class="bi bi-box-seam me-1"></i>Deliver</a>
            <?php elseif ($o['status'] === 'DLV'): ?>
              <a href="index.php?page=production&action=teco&id=<?= $o['id'] ?>" class="btn btn-outline-erp btn-action" onclick="return confirm('Tutup teknis?')"><i class="bi bi-check2-all me-1"></i>TECO</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalPRD" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="index.php?page=production">
        <input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-gear-wide-connected me-2"></i>Buat Production Order</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Material (Barang Jadi)</label>
            <select name="material_id" class="form-select" required>
              <option value="">— Pilih —</option>
              <?php foreach ($materials as $m): ?>
                <option value="<?= $m['id'] ?>"><?= esc($m['code']) ?> — <?= esc($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Jumlah Rencana</label>
            <input type="number" name="quantity" class="form-control" value="1" min="1" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" name="start_date" class="form-control" value="<?= today() ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-erp">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>