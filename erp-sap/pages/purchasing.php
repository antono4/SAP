<?php
// ============================================================
// Materials Management (MM) - Procure-to-Pay
// ============================================================
$pdo = db();
$msg = '';
$err = '';

$action = isset($_POST['action']) ? $_POST['action'] : '';
$action2 = isset($_GET['action']) ? $_GET['action'] : '';

if ($action2 === 'approve_pr') {
    try {
        $id=(int)$_GET['id'];
        $pdo->prepare("UPDATE purchase_requisitions SET status='APPROVED' WHERE id=?")->execute([$id]);
        $msg='PR disetujui.';
    } catch (Throwable $e) {
        $err=$e->getMessage();
    }
}

if ($action2 === 'cancel_po') {
    try {
        $id=(int)$_GET['id'];
        $pdo->prepare("UPDATE purchase_orders SET status='CANCELLED' WHERE id=?")->execute([$id]);
        $msg='PO dibatalkan.';
    } catch (Throwable $e) {
        $err=$e->getMessage();
    }
}

// ---------- Aksi POST ----------
if ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if ($action === 'create_pr') {
            $mid=(int)$_POST['material_id'];
            $qty=(int)$_POST['quantity'];
            $vid=(int)$_POST['vendor_id'];
            if ($mid<=0) { throw new Exception('Pilih material.'); }
            if ($qty<=0) { throw new Exception('Jumlah harus lebih dari  ́0.'); }
            $prNo=doc_number('PR');
            $st=$pdo->prepare("INSERT INTO purchase_requisitions (pr_number,material_id,quantity,vendor_id,status,requested_date) VALUES (?,?,?,?,?,?)");
            if ($vid>0) {
                $st->execute([$prNo,$mid,$qty,$vid,'NEW',today()]);
            } else {
                $st->execute([$prNo,$mid,$qty,null,'NEW',today()]);
            }
            $msg='PR '.$prNo.' dibuat.';
        }

        if ($action === 'create_po') {
            $prId=(int)$_POST['pr_id'];
            $vid=(int)$_POST['vendor_id'];
            $mid=(int)$_POST['material_id'];
            $qty=(int)$_POST['quantity'];
            $price=(float)$_POST['unit_price'];
            if ($vid<=0) { throw new Exception('Pilih vendor.'); }
            if ($mid<=0) { throw new Exception('Pilih material.'); }
            if ($qty<=0) { throw new Exception('Jumlah tidak valid.'); }
            $total=$qty*$price;
            $poNo=doc_number('PO');
            $st=$pdo->prepare("INSERT INTO purchase_orders (po_number,vendor_id,material_id,quantity,unit_price,total_amount,status,order_date) VALUES (?,?,?,?,?,?,?,?)");
            $st->execute([$poNo,$vid,$mid,$qty,$price,$total,'OPEN',today()]);
            if ($prId>0) {
                $st=$pdo->prepare("UPDATE purchase_requisitions SET status='ORDERED' WHERE id=?");
                $st->execute([$prId]);
            }
            $msg='PO '.$poNo.' dibuat.';
        }

        if ($action === 'receive') {
            $poId=(int)$_POST['po_id'];
            $st=$pdo->prepare("SELECT p.po_number,p.material_id,p.quantity,p.total_amount,p.status FROM purchase_orders p WHERE p.id=?");
            $st->execute([$poId]);
            $po=$st->fetch();
            if (!$po) { throw new Exception('PO tidak ditemukan.'); }
            if ($po['status'] === 'CANCELLED') { throw new Exception('PO dibatalkan.'); }
            $qty=(int)$po['quantity'];
            $mid=(int)$po['material_id'];
            $poNo=$po['po_number'];
            $st=$pdo->prepare("UPDATE materials SET stock=stock+? WHERE id=?");
            $st->execute([$qty,$mid]);
            $st=$pdo->prepare("INSERT INTO goods_movements (material_id,movement_type,quantity,reference) VALUES (?,?,?,?)");
            $st->execute([$mid,'101',$qty,$poNo]);
            $amt=(float)$po['total_amount'];
            $invId=(int)$pdo->query("SELECT id FROM accounts WHERE code='120000'")->fetchColumn();
            $apId=(int)$pdo->query("SELECT id FROM accounts WHERE code='200000'")->fetchColumn();
            $je=$pdo->prepare("INSERT INTO journal_entries (entry_number,account_id,debit,credit,description,post_date) VALUES (?,?,?,?,?,?)");
            $je->execute([doc_number('JE'),$invId,$amt,0,'Penerimaan '.$poNo,today()]);
            $je->execute([doc_number('JE'),$apId,0,$amt,'Utang '.$poNo,today()]);
            $st=$pdo->prepare("UPDATE purchase_orders SET status='RECEIVED' WHERE id=?");
            $st->execute([$poId]);
            $msg='Goods Receipt diposting. Stok bertambah.';
        }

        if ($action === 'invoice_po') {
            $poId=(int)$_POST['po_id'];
            $pdo->prepare("UPDATE purchase_orders SET status='INVOICED' WHERE id=?")->execute([$poId]);
            $msg='Invoice verification selesai. PO INVOICED.';
        }

        if ($action === 'pay_po') {
            $poId=(int)$_POST['po_id'];
            $st=$pdo->prepare("SELECT p.po_number,p.total_amount FROM purchase_orders p WHERE p.id=?");
            $st->execute([$poId]);
            $po=$st->fetch();
            if (!$po) { throw new Exception('PO tidak ditemukan.'); }
            $amt=(float)$po['total_amount'];
            $poNo=$po['po_number'];
            $apId=(int)$pdo->query("SELECT id FROM accounts WHERE code='200000'")->fetchColumn();
            $cashId=(int)$pdo->query("SELECT id FROM accounts WHERE code='100000'")->fetchColumn();
            $je=$pdo->prepare("INSERT INTO journal_entries (entry_number,account_id,debit,credit,description,post_date) VALUES (?,?,?,?,?,?)");
            $je->execute([doc_number('JE'),$apId,$amt,0,'Pembayaran '.$poNo,today()]);
            $je->execute([doc_number('JE'),$cashId,0,$amt,'Bayar '.$poNo,today()]);
            $st=$pdo->prepare("UPDATE purchase_orders SET status='PAID' WHERE id=?");
            $st->execute([$poId]);
            $msg='Pembayaran ke vendor selesai.';
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $err=$e->getMessage();
    }
}

// ---------- Data ----------
$prs=$pdo->query("SELECT r.id,r.pr_number,r.material_id,r.quantity,r.vendor_id,r.status,m.code AS material_code,m.name AS material_name,v.name AS vendor_name FROM purchase_requisitions r JOIN materials m ON m.id=r.material_id LEFT JOIN vendors v ON v.id=r.vendor_id ORDER BY r.id DESC")->fetchAll();
$pos=$pdo->query("SELECT p.id,p.po_number,p.vendor_id,p.material_id,p.quantity,p.unit_price,p.total_amount,p.status,m.code AS material_code,m.name AS material_name,v.name AS vendor_name FROM purchase_orders p JOIN materials m ON m.id=p.material_id JOIN vendors v ON v.id=p.vendor_id ORDER BY p.id DESC")->fetchAll();
$materials=$pdo->query("SELECT * FROM materials ORDER BY name")->fetchAll();
$vendors=$pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();
?>

<?php if ($msg): ?>
  <div class="alert alert-success alert-dismissible fade show" data-auto-dismiss><i class="bi bi-check-circle me-2"></i><?= esc($msg) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i><?= esc($err) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- ============ PR ============ -->
<div class="card mb-4">
  <div class="card-header d-flex align-items-center">
    <i class="bi bi-file-earmark-text me-2 text-primary"></i> Purchase Requisition (PR
    <button class="btn btn-erp btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#modalPR"><i class="bi bi-plus-lg me-1"></i>Buat PR</button>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>No. PR</th><th>Material</th><th class="text-end">Qty</th><th>Vendor</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php if (!$prs): ?>
        <tr><td colspan="6"><div class="erp-empty"><i class="bi bi-inbox"></i>Belum ada PR</div></td></tr>
      <?php else: foreach ($prs as $pr): ?>
        <?php
        $matName=''; $matCode=''; $matPrice=0;
        foreach ($materials as $mm) { if ((int)$mm['id']===(int)$pr['material_id']) { $matName=$mm['name']; $matCode=$mm['code']; $matPrice=(float)$mm['price']; break; } }
        $vendorName='';
        foreach ($vendors as $vv) { if ((int)$vv['id']===(int)$pr['vendor_id']) { $vendorName=$vv['name']; break; } }
        ?>
        <tr>
          <td class="text-mono"><?= esc($pr['pr_number']) ?></td>
          <td><span class="text-mono"><?= esc($matCode) ?></span> — <?= esc($matName) ?></td>
          <td class="text-end"><?= (int)$pr['quantity'] ?></td>
          <td><?= $vendorName ? esc($vendorName) : '<span class="text-muted">—</span>' ?></td>
          <td><?= badge($pr['status']) ?></td>
          <td class="text-end">
            <?php if ($pr['status'] === 'NEW'): ?>
              <a href="index.php?page=purchasing&action=approve_pr&id=<?= $pr['id'] ?>" class="btn btn-outline-erp btn-action text-success" onclick="return confirm('Setujui PR ini?')"><i class="bi bi-check2 me-1"></i>Approve</a>
            <?php elseif ($pr['status'] === 'APPROVED' && $vendorName !== '' && $matCode !== ''): ?>
              <form method="post" action="index.php?page=purchasing" class="d-inline">
                <input type="hidden" name="action" value="create_po">
                <input type="hidden" name="pr_id" value="<?= $pr['id'] ?>">
                <input type="hidden" name="vendor_id" value="<?= (int)$pr['vendor_id'] ?>">
                <input type="hidden" name="material_id" value="<?= (int)$pr['material_id'] ?>">
                <input type="hidden" name="quantity" value="<?= (int)$pr['quantity'] ?>">
                <input type="hidden" name="unit_price" value="<?= $matPrice ?>">
                <button class="btn btn-outline-erp btn-action"><i class="bi bi-bag-check me-1"></i>Buat PO</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ PO ============ -->
<div class="card">
  <div class="card-header d-flex align-items-center">
    <i class="bi bi-bag-check me-2 text-success"></i> Purchase Order (PO
    <button class="btn btn-outline-erp btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#modalPO"><i class="bi bi-plus-lg me-1"></i>Buat PO Langsung</button>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>No. PO</th><th>Vendor</th><th>Material</th><th class="text-end">Qty</th><th class="text-end">Total</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php if (!$pos): ?>
        <tr><td colspan="7"><div class="erp-empty"><i class="bi bi-inbox"></i>Belum ada PO</div></td></tr>
      <?php else: foreach ($pos as $po): ?>
        <?php
        $matName=''; $matCode='';
        foreach ($materials as $mm) { if ((int)$mm['id']===(int)$po['material_id']) { $matName=$mm['name']; $matCode=$mm['code']; break; } }
        $vendorName='';
        foreach ($vendors as $vv) { if ((int)$vv['id']===(int)$po['vendor_id']) { $vendorName=$vv['name']; break; } }
        ?>
        <tr>
          <td class="text-mono"><?= esc($po['po_number']) ?></td>
          <td><?= esc($vendorName) ?></td>
          <td><span class="text-mono"><?= esc($matCode) ?></span> — <?= esc($matName) ?></td>
          <td class="text-end"><?= (int)$po['quantity'] ?></td>
          <td class="text-end fw-semibold"><?= nf($po['total_amount']) ?></td>
          <td><?= badge($po['status']) ?></td>
          <td class="text-end">
            <?php if ($po['status'] === 'OPEN'): ?>
              <form method="post" action="index.php?page=purchasing" class="d-inline">
                <input type="hidden" name="action" value="receive">
                <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                <button class="btn btn-outline-erp btn-action text-success" onclick="return confirm('Post Goods Receipt?')"><i class="bi bi-box-arrow-in-down me-1"></i>Goods Receipt</button>
              </form>
              <a href="index.php?page=purchasing&action=cancel_po&id=<?= $po['id'] ?>" class="btn btn-outline-erp btn-action text-danger" onclick="return confirm('Batalkan PO?')"><i class="bi bi-x-lg me-1"></i>Cancel</a>
            <?php elseif ($po['status'] === 'RECEIVED'): ?>
              <form method="post" action="index.php?page=purchasing" class="d-inline">
                <input type="hidden" name="action" value="invoice_po">
                <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                <button class="btn btn-outline-erp btn-action"><i class="bi bi-receipt me-1"></i>Invoice Verify</button>
              </form>
            <?php elseif ($po['status'] === 'INVOICED'): ?>
              <form method="post" action="index.php?page=purchasing" class="d-inline">
                <input type="hidden" name="action" value="pay_po">
                <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                <button class="btn btn-success btn-action" onclick="return confirm('Bayar ke vendor?')"><i class="bi bi-cash-coin me-1"></i>Payment Out</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal PR -->
<div class="modal fade" id="modalPR" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="index.php?page=purchasing">
        <input type="hidden" name="action" value="create_pr">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Buat PR</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Material</label>
            <select name="material_id" class="form-select" required>
              <option value="">— Pilih —</option>
              <?php foreach ($materials as $m): ?>
                <option value="<?= $m['id'] ?>"><?= esc($m['code']) ?> — <?= esc($m['name']) ?> (stok <?= (int)$m['stock'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Vendor (opsional)</label>
            <select name="vendor_id" class="form-select">
              <option value="0">— Tanpa vendor —</option>
              <?php foreach ($vendors as $v): ?>
                <option value="<?= $v['id'] ?>"><?= esc($v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Jumlah</label>
            <input type="number" name="quantity" class="form-control" value="1" min="1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-erp">Simpan PR</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal PO -->
<div class="modal fade" id="modalPO" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="index.php?page=purchasing">
        <input type="hidden" name="action" value="create_po">
        <input type="hidden" name="pr_id" value="0">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-bag-check me-2"></i>Buat PO Langsung</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Vendor</label>
            <select name="vendor_id" class="form-select" required>
              <option value="">— Pilih —</option>
              <?php foreach ($vendors as $v): ?>
                <option value="<?= $v['id'] ?>"><?= esc($v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Material</label>
            <select name="material_id" class="form-select material-po" required>
              <option value="">— Pilih —</option>
              <?php foreach ($materials as $m): ?>
                <option value="<?= $m['id'] ?>" data-price="<?= $m['price'] ?>"><?= esc($m['code']) ?> — <?= esc($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Jumlah</label>
              <input type="number" name="quantity" class="form-control" value="1" min="1" required>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Harga Satuan (Rp)</label>
              <input type="number" name="unit_price" class="form-control" id="po-unit-price" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-erp">Buat PO</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sel = document.querySelector('.material-po');
  if (sel) {
    sel.onchange = function () {
      var price = this.options[this.selectedIndex].getAttribute('data-price');
      document.getElementById('po-unit-price').value = price ? price : '';
    };
  }
});
</script>